<?php

namespace App\Console\Commands;

use App\Domains\Identity\Services\PermissionCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * consume:auth-events — T6.1
 * Lắng nghe auth.events (topic, durable) và invalidate permission cache.
 *
 * - Queue riêng per service: school_auth_events (quorum, DLQ educonnect.dlx)
 * - Retry: nack(requeue=false) → DLQ; Publish*Job retry 5 lần backoff
 * - Idempotency: Redis SETNX auth:idempotency:{key} EX 86400
 * - Consumer invalidate: DEL user:{id}:permissions
 */
class ConsumeAuthEvents extends Command
{
    protected $signature = 'consume:auth-events {--once : xử lý 1 batch rồi thoát} {--queue=school_auth_events}';
    protected $description = 'Consume auth.events và invalidate permission cache (T6.1)';

    private const EXCHANGE = 'auth.events';

    public function handle(): int
    {
        $queue = $this->option('queue') ?: 'school_auth_events';
        $url = env('RABBITMQ_URL', 'amqp://educonnect:educonnect_dev@rabbitmq:5672/');
        $parts = parse_url($url);
        $host = $parts['host'] ?? 'rabbitmq';
        $port = $parts['port'] ?? 5672;
        $user = $parts['user'] ?? 'educonnect';
        $pass = $parts['pass'] ?? 'educonnect_dev';
        $vhost = ($parts['path'] ?? '/') ?: '/';

        $this->info("📡 ConsumeAuthEvents: {$host}:{$port} vhost={$vhost} exchange=" . self::EXCHANGE . " queue={$queue} ...");

        while (true) {
            try {
                $conn = new AMQPStreamConnection($host, (int)$port, $user, $pass, $vhost);
                $channel = $conn->channel();

                $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);
                $channel->queue_declare(
                    $queue,
                    false,
                    true,
                    false,
                    false,
                    false,
                    [
                        'x-queue-type' => ['S', 'quorum'],
                        'x-dead-letter-exchange' => ['S', 'educonnect.dlx'],
                    ]
                );
                $channel->queue_bind($queue, self::EXCHANGE, 'auth.#');
                // also declare DLQ queue (idempotent)
                $channel->queue_declare($queue . '.dlq', false, true, false, false, false, ['x-queue-type' => ['S','quorum']]);
                $channel->queue_bind($queue . '.dlq', 'educonnect.dlx', '');

                $this->info("✅ Sẵn sàng consume auth.events trên queue: {$queue} (binding auth.#) + DLQ {$queue}.dlq");

                $channel->basic_qos(null, 10, null);
                $channel->basic_consume($queue, 'school-auth-worker', false, false, false, false, function (AMQPMessage $msg) {
                    $this->handleMessage($msg);
                });

                while ($channel->is_consuming()) {
                    $channel->wait(null, true);
                    if ($this->option('once')) {
                        $channel->basic_cancel('school-auth-worker');
                        break;
                    }
                }

                $channel->close();
                $conn->close();
                if ($this->option('once')) break;
            } catch (\Throwable $e) {
                Log::error('ConsumeAuthEvents error: ' . $e->getMessage());
                $this->error('RabbitMQ lỗi: ' . $e->getMessage() . ' — reconnect sau 5s');
                sleep(5);
                if ($this->option('once')) return 1;
            }
        }
        return 0;
    }

    private function handleMessage(AMQPMessage $msg): void
    {
        $body = $msg->getBody();
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            $event = $data['event'] ?? $data['payload']['event'] ?? $msg->getRoutingKey() ?? 'unknown';
            $corrId = $data['correlation_id'] ?? ($msg->has('correlation_id') ? $msg->get('correlation_id') : '-');
            $idemKey = $data['idempotency_key'] ?? ($msg->has('message_id') ? $msg->get('message_id') : null);
            if (!$idemKey && $msg->has('application_headers')) {
                $headers = $msg->get('application_headers')->getNativeData();
                $idemKey = $headers['x-idempotency-key'] ?? $headers['x-idempotency_key'] ?? null;
                if (is_array($idemKey)) $idemKey = $idemKey[0] ?? null;
            }
            $idemKey = $idemKey ?: ($data['idempotency_key'] ?? md5($body));

            // Idempotency: SET NX 24h
            $idemRedisKey = "auth:idempotency:{$idemKey}";
            $isNew = Redis::set($idemRedisKey, '1', 'EX', 86400, 'NX');
            if (!$isNew) {
                Log::info("[corr={$corrId}] auth event duplicate idem={$idemKey} event={$event} → ack (idempotent)");
                $msg->ack();
                return;
            }

            $userId = $data['payload']['user_id'] ?? $data['user_id'] ?? $data['aggregate_id'] ?? null;
            if (!$userId && isset($data['payload']['user']['id'])) $userId = $data['payload']['user']['id'];

            // Invalidate permission cache for affected user(s) — avoid re-publish loop
            $cache = app(PermissionCacheService::class);
            app()->instance('consume.auth.events.reentry', true);
            try {
                if ($userId) {
                    $cache->clearUser((int)$userId);
                    Log::info("[corr={$corrId}] auth invalidate user:{$userId}:permissions event={$event} idem={$idemKey}");
                    $this->info("[corr={$corrId}] invalidate user:{$userId}:permissions ← {$event}");
                } else {
                    if (in_array($event, ['auth.role_updated','auth.permission_updated','auth.permissions_changed'])) {
                        try { $cache->clearAll(); } catch (\Throwable $e) {}
                        Log::info("[corr={$corrId}] auth clearAll event={$event} idem={$idemKey}");
                    }
                }
            } finally {
                app()->forgetInstance('consume.auth.events.reentry');
            }

            $msg->ack();
        } catch (\Throwable $e) {
            Log::error('ConsumeAuthEvents handleMessage error: ' . $e->getMessage() . ' body=' . substr($body,0,500));
            try { $msg->nack(false, false); } catch (\Throwable $ignore) {} // → DLQ
        }
    }
}
