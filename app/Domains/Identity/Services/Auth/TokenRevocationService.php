<?php

namespace App\Domains\Identity\Services\Auth;

use App\Domains\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * T5.1 — Hybrid revoke: jti blacklist (TTL = còn lại access) + token version tv + sid session.
 *
 * Keys (prefix educonnect:auth — dùng chung cho Go):
 *  - blacklist:{jti}          → STRING 1 TTL = ttl còn lại của access token (~10 phút)
 *  - user:{id}:tv             → STRING current token_version (TTL 30 ngày, refresh khi bump)
 *  - sid_revoked:{sid}        → STRING 1 TTL 7 ngày (thu hồi 1 session)
 *
 * Tăng tv khi: đổi pass / khóa tài khoản (is_locked/is_active/status) / logoutAll.
 * Go kiểm tra via Redis: jti blacklist → 401, sid revoked → 401, tv mismatch → 401.
 */
class TokenRevocationService
{
    public const PREFIX = 'educonnect:auth';

    // ── JTI blacklist (logout 1 session) ──────────────────────────

    public function blacklistJti(string $jti, int $ttlSeconds): void
    {
        if ($jti === '' || $ttlSeconds <= 0) return;
        // Clamp 1.. 24h
        $ttl = max(1, min($ttlSeconds, 86400));
        try {
            Redis::setex(self::PREFIX . ":blacklist:{$jti}", $ttl, '1');
        } catch (\Throwable $e) {
            // Redis down → fallback bỏ qua (tymøn blacklist vẫn giữ)
        }
    }

    public function isJtiBlacklisted(string $jti): bool
    {
        if ($jti === '') return false;
        try {
            return (bool) Redis::exists(self::PREFIX . ":blacklist:{$jti}");
        } catch (\Throwable $e) {
            return false;
        }
    }

    // TTL còn lại của access token: exp - now (giây)
    public static function remainingTtlFromExp(?int $exp): int
    {
        if (!$exp) return (int) (config('jwt.ttl', 10) * 60);
        $ttl = $exp - time();
        return max(1, $ttl);
    }

    // ── SID revoke ────────────────────────────────────────────────

    public function revokeSid(string $sid, int $ttlSeconds = 604800): void
    {
        if ($sid === '') return;
        $ttl = max(60, $ttlSeconds);
        try {
            Redis::setex(self::PREFIX . ":sid_revoked:{$sid}", $ttl, '1');
            // xóa mapping sid -> user/family (best effort) để Go không coi là hợp lệ
            Redis::del(self::PREFIX . ":sid:{$sid}");
        } catch (\Throwable $e) {
        }
    }

    public function isSidRevoked(string $sid): bool
    {
        if ($sid === '') return false;
        try {
            return (bool) Redis::exists(self::PREFIX . ":sid_revoked:{$sid}");
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── Token version tv ──────────────────────────────────────────

    public function setUserTv(int $userId, int $tv): void
    {
        try {
            Redis::setex(self::PREFIX . ":user:{$userId}:tv", 60 * 60 * 24 * 30, (string) $tv);
        } catch (\Throwable $e) {
        }
    }

    public function getUserTv(int $userId): ?int
    {
        try {
            $v = Redis::get(self::PREFIX . ":user:{$userId}:tv");
            if ($v === null || $v === '') return null;
            return (int) $v;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function isTvStale(int $userId, int $claimTv): bool
    {
        $stored = $this->getUserTv($userId);
        if ($stored === null) return false; // chưa cache → không chặn (DB sẽ chặn ở middleware)
        return $claimTv !== $stored;
    }

    /**
     * Tăng token_version và đồng bộ Redis.
     * Dùng raw query để tránh observer đệ quy.
     * @return int new tv
     */
    public function bumpTokenVersion(User $user): int
    {
        DB::connection('identity')->table('users')
            ->where('id', $user->id)
            ->update(['token_version' => DB::raw('token_version + 1')]);

        /** @var User $fresh */
        $fresh = User::on('identity')->find($user->id);
        $newTv = (int) ($fresh?->token_version ?? ($user->token_version + 1));
        $this->setUserTv((int) $user->id, $newTv);

        try { app(\App\Domains\Identity\Services\PermissionCacheService::class)->clearUser($user->id); } catch (\Throwable $e) {}

        return $newTv;
    }

    /**
     * Bump bằng userId (không cần load model), dùng khi chỉ có id.
     */
    public function bumpTokenVersionById(int $userId): int
    {
        DB::connection('identity')->table('users')
            ->where('id', $userId)
            ->update(['token_version' => DB::raw('token_version + 1')]);
        $fresh = User::on('identity')->find($userId);
        $newTv = (int) ($fresh?->token_version ?? 1);
        $this->setUserTv($userId, $newTv);
        try { app(\App\Domains\Identity\Services\PermissionCacheService::class)->clearUser($userId); } catch (\Throwable $e) {}
        return $newTv;
    }
}
