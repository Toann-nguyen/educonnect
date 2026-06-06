<?php

namespace App\Repositories\Contracts;

use App\Models\EmailVerification;

interface EmailVerificationRepositoryInterface
{
    /**
     * Tạo hoặc cập nhật token verify (upsert theo user_id)
     * Tránh tồn tại nhiều token cho cùng 1 user
     */
    public function upsert(int $userId, string $tokenHash, \Carbon\Carbon $expiresAt): EmailVerification;

    /**
     * Tìm record hợp lệ theo token_hash
     * Điều kiện: verified_at IS NULL
     */
    public function findByTokenHash(string $tokenHash): ?EmailVerification;

    /**
     * Đánh dấu đã verify
     */
    public function markAsVerified(int $id): bool;
}
