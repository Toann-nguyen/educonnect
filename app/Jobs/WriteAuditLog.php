<?php

namespace App\Jobs;

use App\Models\AuditLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job ghi audit log bất đồng bộ.
 * Chạy trên queue 'audit' riêng để không tranh tài nguyên với queue chính.
 * Retry tối đa 3 lần, delay 5s/lần để tránh overload DB khi spike.
 */
class WriteAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Retry tối đa 3 lần nếu DB lỗi tạm thời
    public int $tries = 3;

    // Delay giữa các lần retry (giây)
    public int $backoff = 5;

    // Không cần timeout dài – ghi DB đơn giản
    public int $timeout = 10;

    public function __construct(
        private readonly ?int   $userId,
        private readonly string $action,
        private readonly string $ipAddress,
        private readonly string $userAgent,
        private readonly array  $metadata = [],
    ) {}

    public function handle(): void
    {
        AuditLog::create([
            'user_id'    => $this->userId,
            'action'     => $this->action,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'metadata'   => $this->metadata,
        ]);
    }

    /**
     * Khi job thất bại hoàn toàn (hết retry) → ghi log application thay vì mất data.
     */
    public function failed(\Throwable $exception): void
    {
        \Illuminate\Support\Facades\Log::critical('WriteAuditLog job failed permanently', [
            'user_id'    => $this->userId,
            'action'     => $this->action,
            'ip_address' => $this->ipAddress,
            'metadata'   => $this->metadata,
            'error'      => $exception->getMessage(),
        ]);
    }
}
