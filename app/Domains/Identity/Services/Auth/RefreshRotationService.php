<?php

namespace App\Domains\Identity\Services\Auth;

use App\Domains\Identity\Models\RefreshToken;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * T1.3 — Redis Refresh Rotation + grace 10-30s + reuse detection (family/session).
 *
 * Lưu user_id / sid / jti / tv, hỗ trợ revoke 1 vs all.
 *
 * Keys (prefix educonnect:auth):
 *  - refresh:{hash}           → HASH {user_id,sid,jti,tv,family,ip,ua,expires_at,created_at} TTL 7-30d
 *  - grace:{old_hash}         → STRING new_hash TTL 10-30s (idempotent retry window)
 *  - family:{family_id}       → SET {hash…} TTL 7-30d (family = chuỗi rotation từ 1 login)
 *  - family_revoked:{family}  → STRING 1 TTL 7d (tombstone để phát hiện reuse sau revoke)
 *  - user:{user_id}:families  → SET {family…}
 *  - sid:{sid}                → HASH {user_id,family,jti,tv}
 *
 * Grace = khoảng 10-30s cho phép dùng lại old token (do parallel request / retry).
 * Reuse detection: nếu old token bị dùng lại NGOÀI grace window → coi là token theft
 *   → revoke toàn bộ family (hoặc all sessions nếu policy strict).
 */
class RefreshRotationService
{
    private const PREFIX = 'educonnect:auth';
    private const GRACE_DEFAULT = 30; // seconds (10-30s theo spec)
    private const GRACE_MIN = 10;
    private const GRACE_MAX = 30;

    /** @return int grace seconds 10-30s */
    public static function graceSeconds(): int
    {
        $cfg = (int) (config('jwt.refresh_grace') ?? env('JWT_REFRESH_GRACE', self::GRACE_DEFAULT));
        if ($cfg < self::GRACE_MIN) $cfg = self::GRACE_MIN;
        if ($cfg > self::GRACE_MAX) $cfg = self::GRACE_MAX;
        return $cfg;
    }

    /** Generate a new refresh token + persist DB+Redis for a given family/sid. */
    public function issue(User $user, ?array $deviceInfo, string $ip, ?string $family = null, ?string $sid = null): array
    {
        $family ??= (string) Str::uuid();
        $sid    ??= (string) Str::uuid();
        $jti      = (string) Str::uuid();
        $tv       = (int) ($user->token_version ?? 1);

        $raw  = Str::random(60);
        $hash = hash('sha256', $raw);

        $expiresAt = now()->addMinutes((int) config('jwt.refresh_ttl', 10080)); // 7d default
        $fingerprint = $deviceInfo['fingerprint'] ?? hash('sha256', (request()->userAgent() ?? 'ua') . '|' . $ip);

        // Persist DB — add columns if migration ran, fallback gracefully
        $payload = [
            'user_id'            => $user->id,
            'token_hash'         => $hash,
            'device_info'        => $deviceInfo ?? ['user_agent' => request()->userAgent()],
            'device_fingerprint' => $fingerprint,
            'ip_address'         => $ip,
            'expires_at'         => $expiresAt,
        ];
        // optional columns (migration T1.3)
        foreach (['sid' => $sid, 'jti' => $jti, 'family' => $family, 'tv' => $tv] as $k => $v) {
            if ($this->hasColumn('refresh_tokens', $k)) {
                $payload[$k] = $v;
            }
        }
        // family/sid/jti are stored in Redis regardless of DB columns
        $refreshToken = RefreshToken::create($payload);

        UserSession::create([
            'user_id'          => $user->id,
            'refresh_token_id' => $refreshToken->id,
            'device_name'      => $deviceInfo['device_name'] ?? 'Unknown Device',
            'ip_address'       => $ip,
            'user_agent'       => request()->userAgent(),
            'last_active_at'   => now(),
        ]);

        $this->storeRefresh($hash, [
            'user_id'   => $user->id,
            'sid'       => $sid,
            'jti'       => $jti,
            'tv'        => $tv,
            'family'    => $family,
            'ip'        => $ip,
            'ua'        => request()->userAgent() ?? '',
            'expires_at'=> $expiresAt->getTimestamp(),
            'created_at'=> now()->getTimestamp(),
        ], (int) $expiresAt->diffInSeconds(now()));

        // family membership + user families + sid mapping
        $this->addToFamily($family, $hash, (int) $expiresAt->diffInSeconds(now()));
        $this->addUserFamily($user->id, $family);
        $this->storeSid($sid, [
            'user_id' => $user->id,
            'family'  => $family,
            'jti'     => $jti,
            'tv'      => $tv,
        ], (int) $expiresAt->diffInSeconds(now()));

        return [
            'raw'        => $raw,
            'hash'       => $hash,
            'sid'        => $sid,
            'jti'        => $jti,
            'family'     => $family,
            'tv'         => $tv,
            'expires_at' => $expiresAt,
            'id'         => $refreshToken->id,
        ];
    }

    /**
     * Rotate: old_hash -> new token in same family/sid.
     * Returns meta of new token or replays grace.
     */
    public function rotate(string $oldHash, User $user, ?array $deviceInfo, string $ip): array
    {
        // Check family_revoked tombstone → already compromised
        $meta = $this->getRefresh($oldHash);
        // If not in Redis, check grace (idempotent retry)
        if (!$meta) {
            $graceNew = $this->getGrace($oldHash);
            if ($graceNew) {
                $newMeta = $this->getRefresh($graceNew);
                if ($newMeta) {
                    // Idempotent: return existing new token without creating duplicate
                    $newRaw = null; // caller must resolve raw? We store raw not recoverable — so we re-issue? Use grace to return cached raw.
                    // Instead, store raw in grace payload: grace:{old} -> {new_hash,new_raw}
                    $cached = $this->getGracePayload($oldHash);
                    if ($cached && isset($cached['raw'])) {
                        return [
                            'raw'    => $cached['raw'],
                            'hash'   => $graceNew,
                            'sid'    => $newMeta['sid'] ?? null,
                            'jti'    => $newMeta['jti'] ?? null,
                            'family' => $newMeta['family'] ?? null,
                            'tv'     => $newMeta['tv'] ?? null,
                            'replayed' => true,
                        ];
                    }
                }
            }
            // Hard reuse detection: token was revoked/expired and reused outside grace
            // Check DB: if revoked_at not null → theft
            $record = RefreshToken::where('token_hash', $oldHash)->first();
            if ($record && $record->revoked_at !== null) {
                $family = $record->family ?? $this->findFamilyByHash($oldHash) ?? null;
                if ($family) {
                    $this->revokeFamily($family, (int) $record->user_id, 'reuse_detected');
                } else {
                    // Fallback: revoke all sessions of user
                    $this->revokeAll((int) $record->user_id);
                }
                // Also bump not yet? Policy: revoke family only. Optionally revoke all + bump tv.
                // Throw to trigger 401 + audit
                throw new \App\Domains\Identity\Exceptions\Auth\InvalidCredentialsException('Invalid or expired refresh token — reuse detected, family revoked');
            }
            // Not found anywhere → invalid
            throw new \App\Domains\Identity\Exceptions\Auth\InvalidCredentialsException('Invalid or expired refresh token');
        }

        // Verify user match
        if ((int) $meta['user_id'] !== (int) $user->id) {
            throw new \App\Domains\Identity\Exceptions\Auth\InvalidCredentialsException('Invalid or expired refresh token');
        }
        // Verify tv not stale (token_version bumped)
        if ((int) $meta['tv'] !== (int) ($user->token_version ?? 1)) {
            throw new \App\Domains\Identity\Exceptions\Auth\InvalidCredentialsException('Invalid or expired refresh token — token version mismatch');
        }
        // Check family revoked tombstone
        if (!empty($meta['family']) && $this->isFamilyRevoked($meta['family'])) {
            throw new \App\Domains\Identity\Exceptions\Auth\InvalidCredentialsException('Invalid or expired refresh token — family revoked');
        }

        // Perform rotation: create new token in same family/sid
        $new = $this->issue($user, $deviceInfo, $ip, $meta['family'], $meta['sid']);

        // Mark old as rotated with grace window
        $this->markGrace($oldHash, $new['hash'], $new['raw']);
        // Revoke old DB record
        RefreshToken::where('token_hash', $oldHash)->update(['revoked_at' => now(), 'last_used_at' => now()]);
        // Remove sid mapping old jti? Keep sid mapping updated to new jti
        // Delete old refresh key (keep grace only)
        $this->deleteRefresh($oldHash);
        // Remove old hash from family set is optional — keep for audit, so we keep tombstone
        // Clean old UserSession
        // Will be recreated via issue() new session? Remove old session of old hash
        $oldRecord = RefreshToken::where('token_hash', $oldHash)->first();
        if ($oldRecord) {
            UserSession::where('refresh_token_id', $oldRecord->id)->delete();
        }

        // Audit handled by caller
        return array_merge($new, ['replayed' => false]);
    }

    // ── Redis helpers ─────────────────────────────────────────────

    public function storeRefresh(string $hash, array $fields, int $ttlSeconds): void
    {
        $key = self::PREFIX . ":refresh:{$hash}";
        try {
            Redis::hmset($key, $fields);
            Redis::expire($key, max($ttlSeconds, 60));
        } catch (\Throwable $e) {
            // Redis unavailable → continue (DB is source of truth)
        }
    }

    public function getRefresh(string $hash): ?array
    {
        $key = self::PREFIX . ":refresh:{$hash}";
        try {
            $data = Redis::hgetall($key);
            if (empty($data)) return null;
            return $data;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function deleteRefresh(string $hash): void
    {
        try { Redis::del(self::PREFIX . ":refresh:{$hash}"); } catch (\Throwable $e) {}
    }

    public function markGrace(string $oldHash, string $newHash, string $newRaw): void
    {
        $grace = self::graceSeconds();
        try {
            // Simple mapping old->new
            Redis::setex(self::PREFIX . ":grace:{$oldHash}", $grace, $newHash);
            // Extended payload with raw for idempotent replay (short-lived)
            Redis::setex(self::PREFIX . ":grace_raw:{$oldHash}", $grace, json_encode(['hash' => $newHash, 'raw' => $newRaw]));
        } catch (\Throwable $e) {}
    }

    public function getGrace(string $oldHash): ?string
    {
        try {
            $v = Redis::get(self::PREFIX . ":grace:{$oldHash}");
            return $v ?: null;
        } catch (\Throwable $e) { return null; }
    }

    public function getGracePayload(string $oldHash): ?array
    {
        try {
            $v = Redis::get(self::PREFIX . ":grace_raw:{$oldHash}");
            if (!$v) return null;
            return json_decode($v, true);
        } catch (\Throwable $e) { return null; }
    }

    public function addToFamily(string $family, string $hash, int $ttl): void
    {
        try {
            $k = self::PREFIX . ":family:{$family}";
            Redis::sadd($k, $hash);
            Redis::expire($k, max($ttl, 3600));
        } catch (\Throwable $e) {}
    }

    public function findFamilyByHash(string $hash): ?string
    {
        // Reverse lookup not indexed — caller should prefer meta family. Fallback via scan disabled.
        return null;
    }

    public function addUserFamily(int $userId, string $family): void
    {
        try {
            $k = self::PREFIX . ":user:{$userId}:families";
            Redis::sadd($k, $family);
            Redis::expire($k, 60*60*24*30); // 30d
        } catch (\Throwable $e) {}
    }

    public function isFamilyRevoked(string $family): bool
    {
        try {
            return (bool) Redis::exists(self::PREFIX . ":family_revoked:{$family}");
        } catch (\Throwable $e) { return false; }
    }

    public function storeSid(string $sid, array $fields, int $ttl): void
    {
        try {
            $k = self::PREFIX . ":sid:{$sid}";
            Redis::hmset($k, $fields);
            Redis::expire($k, max($ttl, 3600));
        } catch (\Throwable $e) {}
    }

    /** Revoke ONE session/sid (destroy single refresh hash). */
    public function revokeOne(string $hash, ?int $userId = null): void
    {
        $meta = $this->getRefresh($hash);
        $family = $meta['family'] ?? null;
        $sid    = $meta['sid'] ?? null;

        $this->deleteRefresh($hash);
        try { Redis::del(self::PREFIX . ":grace:{$hash}"); Redis::del(self::PREFIX . ":grace_raw:{$hash}"); } catch (\Throwable $e) {}

        if ($sid) {
            try { Redis::del(self::PREFIX . ":sid:{$sid}"); } catch (\Throwable $e) {}
        }
        if ($family) {
            try { Redis::srem(self::PREFIX . ":family:{$family}", $hash); } catch (\Throwable $e) {}
        }
        // DB side handled by caller
    }

    /** Revoke entire family (reuse detection) — invalidates all tokens derived from one login. */
    public function revokeFamily(string $family, int $userId, string $reason = 'revoked'): void
    {
        $familyKey = self::PREFIX . ":family:{$family}";
        $revokedKey = self::PREFIX . ":family_revoked:{$family}";
        try {
            $hashes = Redis::smembers($familyKey) ?: [];
            foreach ($hashes as $h) {
                $this->deleteRefresh($h);
                Redis::del(self::PREFIX . ":grace:{$h}");
                Redis::del(self::PREFIX . ":grace_raw:{$h}");
            }
            Redis::del($familyKey);
            // Tombstone so future reuse with any hash in family is rejected within grace detection
            Redis::setex($revokedKey, 60*60*24*7, json_encode(['user_id'=>$userId,'reason'=>$reason,'at'=>now()->toIso8601String()]));
            Redis::srem(self::PREFIX . ":user:{$userId}:families", $family);
        } catch (\Throwable $e) {}

        // DB: revoke all tokens of family if column exists, else revoke all user tokens of that family via device? fallback revoke by user+family
        if ($this->hasColumn('refresh_tokens','family')) {
            RefreshToken::where('family', $family)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            // cleanup sessions
            $ids = RefreshToken::where('family', $family)->pluck('id');
            if ($ids->isNotEmpty()) {
                UserSession::whereIn('refresh_token_id', $ids)->delete();
                // also delete sid mappings for that family
            }
        } else {
            // Fallback: revoke all active tokens of user (conservative)
            RefreshToken::where('user_id',$userId)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
            UserSession::where('user_id',$userId)->delete();
        }
        // Clear sid keys of that family — best-effort scan limited to known hashes
    }

    /** Revoke ALL sessions of a user (logoutAll) + delete Redis families/sids + bump tv handled by caller. */
    public function revokeAll(int $userId): void
    {
        $familiesKey = self::PREFIX . ":user:{$userId}:families";
        try {
            $families = Redis::smembers($familiesKey) ?: [];
            foreach ($families as $fam) {
                $this->revokeFamily($fam, $userId, 'revoke_all');
            }
            // Also sweep any remaining refresh keys for user via DB hashes
            $hashes = RefreshToken::where('user_id',$userId)->whereNull('revoked_at')->pluck('token_hash');
            foreach ($hashes as $h) {
                $this->deleteRefresh($h);
            }
            Redis::del($familiesKey);
            // Sweep sid keys: pattern educonnect:auth:sid:* — avoid KEYS in prod, use scan via SMEMBERS instead. Best-effort skip if not tracked.
        } catch (\Throwable $e) {}

        // DB side: revoke + delete sessions (caller also does)
        RefreshToken::where('user_id',$userId)->whereNull('revoked_at')->update(['revoked_at'=>now()]);
        UserSession::where('user_id',$userId)->delete();
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table.'.'.$column;
        if (array_key_exists($key, $cache)) return $cache[$key];
        try {
            $cache[$key] = \Illuminate\Support\Facades\Schema::connection('identity')->hasColumn($table, $column);
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }
}
