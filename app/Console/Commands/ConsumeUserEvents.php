<?php

namespace App\Console\Commands;

use App\Models\UsersReadModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * consume:user-events — lắng nghe user_events (RabbitMQ) và sync users_read_model
 *
 * Task 3.2: Identity publish UserCreated/UserUpdated → Academic/Library lưu
 * bảng bóng users_read_model để query nhanh không gọi API ngược về Identity.
 *
 * Chạy daemon:  php artisan consume:user-events
 * Chạy 1 lần:   php artisan consume:user-events --once
 */
class ConsumeUserEvents extends Command
{
    protected $signature = 'consume:user-events {--once : xử lý xong rồi thoát}';

    protected $description = 'Consume user_events từ RabbitMQ và sync users_read_model';

    private const EXCHANGE = 'user_events';
    private const QUEUE = 'school_users_sync';

    public function handle(): int
    {
        $url = env('RABBITMQ_URL', 'amqp://educonnect:educonnect_dev@rabbitmq:5672');
        $parts = parse_url($url);
        $host = $parts['host'] ?? 'rabbitmq';
        $port = $parts['port'] ?? 5672;
        $user = $parts['user'] ?? 'educonnect';
        $pass = $parts['pass'] ?? 'educonnect_dev';
        $vhost = ($parts['path'] ?? '/') ?: '/';

        $this->info("📡 ConsumeUserEvents: kết nối {$host}:{$port} vhost={$vhost} ...");

        while (true) {
            try {
                $conn = new AMQPStreamConnection($host, $port, $user, $pass, $vhost);
                $channel = $conn->channel();

                $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);
                $channel->queue_declare(
                    self::QUEUE,
                    false,
                    true,
                    false,
                    false,
                    false,
                    [
                        'x-dead-letter-exchange' => ['S', 'educonnect.dlx'],
                    ]
                );
                $channel->queue_bind(self::QUEUE, self::EXCHANGE, 'user.#');

                $this->info("✅ Sẵn sàng consume trên queue: " . self::QUEUE . " (binding user.#)");

                $channel->basic_consume(self::QUEUE, 'school-users-worker', false, false, false, false, function (AMQPMessage $msg) {
                    $this->handleMessage($msg);
                });

                while ($channel->is_consuming()) {
                    $channel->wait(null, true);
                    if ($this->option('once')) {
                        $channel->basic_cancel('school-users-worker');
                    }
                }

                $channel->close();
                $conn->close();
            } catch (\Throwable $e) {
                Log::error('ConsumeUserEvents error: ' . $e->getMessage());
                $this->error('RabbitMQ lỗi: ' . $e->getMessage() . ' — reconnect sau 5s');
                sleep(5);
            }
        }
    }

    private function handleMessage(AMQPMessage $msg): void
    {
        try {
            $data = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $corrId = $data['correlation_id'] ?? ($msg->has('application_headers') ? $msg->get('application_headers')->getNativeData()['x-correlation-id'] ?? '-' : '-');

            if (isset($data['user']['id'])) {
                $u = $data['user'];
                UsersReadModel::updateOrCreate(
                    ['id' => $u['id']],
                    [
                        'name'      => $u['name'] ?? '',
                        'email'     => $u['email'] ?? '',
                        'roles'     => $u['roles'] ?? [],
                        'is_active' => $u['is_active'] ?? true,
                    ]
                );
                Log::info("[corr={$corrId}] sync {$data['event']} → users_read_model id={$u['id']} ({$u['email']})");
                $this->info("[corr={$corrId}] sync {$data['event']} → users_read_model id={$u['id']} ({$u['email']})");
            } else {
                Log::warning('User event thiếu user payload, drop: ' . $msg->getBody());
            }

            $msg->ack();
        } catch (\Throwable $e) {
            Log::error('handleMessage error: ' . $e->getMessage());
            try {
                $msg->nack(false, false); // → DLX
            } catch (\Throwable $ignore) {
            }
        }
    }
}
