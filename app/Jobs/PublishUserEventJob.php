<?php

namespace App\Jobs;

use App\Domains\Identity\Models\OutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * PublishUserEventJob — school monolith mirror (identity connection)
 * Publishes one outbox row to RabbitMQ user_events topic.
 */
class PublishUserEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function __construct(public int $outboxId) {}

    public function handle(): void
    {
        $outbox = OutboxEvent::find($this->outboxId);
        if (!$outbox) {
            Log::warning("PublishUserEventJob: outbox {$this->outboxId} not found");
            return;
        }
        if ($outbox->status === 'published') {
            return;
        }

        $url = env('RABBITMQ_URL', 'amqp://educonnect:educonnect_dev@rabbitmq:5672/');
        $parts = parse_url($url);
        $host = $parts['host'] ?? 'rabbitmq';
        $port = $parts['port'] ?? 5672;
        $user = $parts['user'] ?? 'educonnect';
        $pass = $parts['pass'] ?? 'educonnect_dev';
        $vhost = ($parts['path'] ?? '/') ?: '/';

        try {
            $conn = new AMQPStreamConnection($host, (int)$port, $user, $pass, $vhost);
            $ch = $conn->channel();
            $exchange = 'user_events';
            $ch->exchange_declare($exchange, 'topic', false, true, false);

            $payload = $outbox->payload;
            if (!isset($payload['correlation_id'])) {
                $payload['correlation_id'] = (string) \Illuminate\Support\Str::uuid();
            }

            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $msg = new AMQPMessage($body, [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'correlation_id' => $payload['correlation_id'],
                'timestamp' => time(),
                'app_id' => 'identity',
            ]);

            $ch->basic_publish($msg, $exchange, $outbox->event_type);
            $ch->close();
            $conn->close();

            $outbox->update([
                'status' => 'published',
                'published_at' => now(),
                'attempts' => $outbox->attempts + 1,
            ]);

            Log::info("[outbox {$outbox->id}] published {$outbox->event_type} aggregate={$outbox->aggregate_id} corr={$payload['correlation_id']}");
        } catch (\Throwable $e) {
            Log::error("[outbox {$outbox->id}] publish failed attempt " . ($outbox->attempts + 1) . ": " . $e->getMessage());
            $outbox->increment('attempts');
            $outbox->update([
                'status' => $this->attempts() >= $this->tries - 1 ? 'failed' : 'pending',
                'next_retry_at' => now()->addSeconds($this->backoff()[$this->attempts()] ?? 60),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        OutboxEvent::where('id', $this->outboxId)->update([
            'status' => 'failed',
            'next_retry_at' => now()->addMinutes(5),
        ]);
        Log::error("[outbox {$this->outboxId}] final failure: " . $e->getMessage());
    }
}
