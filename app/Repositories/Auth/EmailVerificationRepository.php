<?php

namespace App\Repositories\Auth;

use App\Models\EmailVerification;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use Carbon\Carbon;

class EmailVerificationRepository implements EmailVerificationRepositoryInterface
{
    public function __construct(
        private readonly EmailVerification $model
    ) {}

    public function upsert(int $userId, string $tokenHash, Carbon $expiresAt): EmailVerification
    {
        // updateOrCreate vì user_id là unique
        // → ghi đè token cũ nếu user bấm "gửi lại"
        return $this->model->updateOrCreate(
            ['user_id' => $userId],
            [
                'token_hash'  => $tokenHash,
                'expires_at'  => $expiresAt,
                'verified_at' => null,
                'created_at'  => now(),
            ]
        );
    }

    public function findByTokenHash(string $tokenHash): ?EmailVerification
    {
        return $this->model
            ->where('token_hash', $tokenHash)
            ->whereNull('verified_at')
            ->first();
    }

    public function markAsVerified(int $id): bool
    {
        return (bool) $this->model
            ->where('id', $id)
            ->update(['verified_at' => now()]);
    }
}
