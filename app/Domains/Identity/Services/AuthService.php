<?php

namespace App\Domains\Identity\Services;

use App\Domains\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domains\Identity\Repositories\Contracts\AuthRepositoryInterface;
use App\Domains\Identity\Repositories\Contracts\EmailVerificationRepositoryInterface;
use App\Domains\Identity\Services\Interface\AuthServiceInterface;
use App\Domains\Identity\Support\RequestIp;
use App\Domains\Identity\Exceptions\Auth\IPSpamException;
use App\Domains\Identity\Exceptions\Auth\AccountLockedException;
use App\Domains\Identity\Exceptions\Auth\CaptchaRequiredException;
use App\Domains\Identity\Exceptions\Auth\InvalidCredentialsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Exception;
use App\Domains\Identity\Jobs\SendVerificationEmail;
use App\Domains\Identity\Jobs\WriteAuditLog;
use App\Domains\Identity\Models\RefreshToken;
use App\Domains\Identity\Models\UserSession;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Services\Auth\RefreshRotationService;
use App\Domains\Identity\Services\PermissionCacheService;

class AuthService implements AuthServiceInterface
{
    /**
     * Redis key prefix for all auth-related keys.
     * Helps namespace keys for debugging and avoids conflicts.
     */
    private const REDIS_PREFIX = 'educonnect:auth';

    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuthRepositoryInterface $authRepository,
        protected EmailVerificationRepositoryInterface $emailVerificationRepository
    ) {}

    public function register(array $data)
    {
        // 1. Tạo user thông qua auth repository
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user = $this->authRepository->create($data);

        // 2. Gán vai trò mặc định
        $user->assignRole('student');

        // 3. Publish event UserCreated lên Redis Stream để Finance/Notify service consume
        Redis::xadd('user.created', '*', [
            'user_id'    => $user->id,
            'email'      => $user->email,
            'name'       => $user->name,
            'role'       => 'student',
            'created_at' => now()->toIso8601String(),
        ]);

        // 4. Publish notification xác minh email lên Redis Stream để Notify service gửi
        Redis::xadd('notifications', '*', [
            'payload' => json_encode([
                'type'    => 'email',
                'to'      => $user->email,
                'subject' => 'Xác minh tài khoản EduConnect của bạn',
                'body'    => "Xin chào {$user->name},<br>Vui lòng xác minh email của bạn.",
            ]),
        ]);

        // 5. Tạo token xác minh email
        $rawToken = Str::random(60);
        $tokenHash = hash('sha256', $rawToken);

        $this->emailVerificationRepository->upsert(
            $user->id,
            $tokenHash,
            now()->addHours(24)
        );

        // 6. Gửi email xác minh qua Queue Job (fallback nếu Notify service chưa lên)
        SendVerificationEmail::dispatch($user, $rawToken);

        // 7. Tự động tạo Access Token (Auto-login)
        $token = auth('api')->login($user);

        return [
            'user'  => $user->load('profile', 'roles'),
            'token' => $token
        ];
    }

    // ========================================================================
    // LOGIN FLOW — các bước được tách thành methods riêng
    // ========================================================================

    public function login(array $credentials)
    {
        $ip    = RequestIp::resolve(request());
        $email = strtolower($credentials['email']);

        // Step 1: Dual sliding window rate limit
        $this->checkRateLimit($ip, $email);

        // Step 2: Account lock check
        $this->isAccountLocked($ip, $email);

        // Step 3: Captcha check (nếu đã sai >= 3 lần)
        $this->requiresCaptcha($ip, $email, $credentials);

        // Step 4-5-6: Find user + verify password
        $user = $this->findAndVerifyPassword($email, $credentials['password'], $ip);

        // Step 7: Reset failed attempts counter
        $this->resetAttempts($ip, $email);

        // Step 8: Update last_login_at
        $this->authRepository->update($user->id, ['last_login_at' => now()]);

        // Step 9: RBAC check
        if (!$user->hasAnyRole(['admin', 'teacher', 'student', 'parent', 'accountant', 'librarian', 'red_scarf', 'principal'])) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Insufficient permissions.');
        }

        // Step 10: 2FA
        if ($user->totp_enabled || $user->phone_2fa_enabled) {
            /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
            $guard        = auth('api');
            $preAuthToken = $guard->claims(['pre_auth' => true])->setTTL(5)->fromUser($user);
            return [
                'requires_2fa'   => true,
                'pre_auth_token' => $preAuthToken,
            ];
        }

        // Step 11: Issue tokens + audit log
        $tokens = $this->issueTokens($user, $credentials['device_info'] ?? null, $ip);
        $this->dispatchAudit($user->id, 'LOGIN_SUCCESS', ['session_id' => $tokens['refresh_token_id']]);

        return [
            'user'          => $user->load('profile', 'roles'),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ];
    }

    /**
     * Step 1: Dual sliding window rate limit.
     * Sử dụng 2 keys trong 1 Lua script (1 round-trip Redis):
     *   - Key 1 (IP):        limit = 30 req/phút — chặn spam tổng thể
     *   - Key 2 (IP+Email):  limit = 10 req/phút — chặn brute force có chủ đích
     *
     * Lua script trả về:
     *   [1, 'allowed', currentCount, 0]           — được phép
     *   [0, 'ip', count, retryAfter]              — bị chặn bởi IP limit
     *   [0, 'pair', count, retryAfter]            — bị chặn bởi IP+Email limit
     */
    private function checkRateLimit(string $ip, string $email): void
    {
        $ipKey    = self::REDIS_PREFIX . ":rate:ip:{$ip}";
        $pairKey  = self::REDIS_PREFIX . ":rate:login:{$ip}:{$email}";
        $now      = microtime(true);
        $window   = 60;  // 1 phút
        $ipLimit  = 30;  // tối đa 30 requests/IP
        $prLimit  = 10;  // tối đa 10 requests/IP+Email
        $ipUid    = (string) Str::uuid();
        $prUid    = (string) Str::uuid();

        $result = Redis::eval(
            "local ipKey       = KEYS[1]\n" .
            "local pairKey     = KEYS[2]\n" .
            "local now         = tonumber(ARGV[1])\n" .
            "local window      = tonumber(ARGV[2])\n" .
            "local ipLimit     = tonumber(ARGV[3])\n" .
            "local pairLimit   = tonumber(ARGV[4])\n" .
            "local ipUniqId    = ARGV[5]\n" .
            "local pairUniqId  = ARGV[6]\n" .
            "local clearBefore = now - window\n" .
            "redis.call('ZREMRANGEBYSCORE', ipKey, '-inf', clearBefore)\n" .
            "redis.call('ZREMRANGEBYSCORE', pairKey, '-inf', clearBefore)\n" .
            "local ipCount   = redis.call('ZCARD', ipKey)\n" .
            "local pairCount = redis.call('ZCARD', pairKey)\n" .
            "if ipCount >= ipLimit then\n" .
            "    local oldest = redis.call('ZRANGE', ipKey, 0, 0, 'WITHSCORES')\n" .
            "    local retryAfter = math.ceil(oldest[2] + window - now)\n" .
            "    return {0, 'ip', ipCount, retryAfter}\n" .
            "end\n" .
            "if pairCount >= pairLimit then\n" .
            "    local oldest = redis.call('ZRANGE', pairKey, 0, 0, 'WITHSCORES')\n" .
            "    local retryAfter = math.ceil(oldest[2] + window - now)\n" .
            "    return {0, 'pair', pairCount, retryAfter}\n" .
            "end\n" .
            "redis.call('ZADD', ipKey, now, ipUniqId)\n" .
            "redis.call('EXPIRE', ipKey, window)\n" .
            "redis.call('ZADD', pairKey, now, pairUniqId)\n" .
            "redis.call('EXPIRE', pairKey, window)\n" .
            "return {1, 'allowed', ipCount + 1, 0}\n",
            2,
            $ipKey,
            $pairKey,
            $now,
            $window,
            $ipLimit,
            $prLimit,
            $ipUid,
            $prUid
        );

        $allowed = (bool) $result[0];

        if (!$allowed) {
            $blockedBy = $result[1];
            $count     = $result[2];
            $retryAfter = $result[3];

            // Log blocked attempt for monitoring
            $this->logBlockedAttempt($ip, $email, $blockedBy, $count);

            throw new IPSpamException(
                message: "Too many login attempts. Retry after {$retryAfter} seconds.",
                retryAfter: $retryAfter,
                blockedBy: $blockedBy
            );
        }
    }

    /**
     * Step 2: Check if the account is locked (IP+Email).
     */
    private function isAccountLocked(string $ip, string $email): void
    {
        $lockKey = self::REDIS_PREFIX . ":lock:{$ip}:{$email}";
        if (Redis::get($lockKey)) {
            throw new AccountLockedException();
        }
    }

    /**
     * Step 3: Check captcha requirement after 3 failed attempts.
     */
    private function requiresCaptcha(string $ip, string $email, array $credentials): void
    {
        $attemptsKey = self::REDIS_PREFIX . ":attempts:{$ip}:{$email}";
        $attempts    = (int) Redis::get($attemptsKey);

        if ($attempts >= 3) {
            $captchaToken = $credentials['captcha_token'] ?? null;

            if (!$captchaToken || $captchaToken !== 'valid_captcha_token') {
                throw new CaptchaRequiredException();
            }
        }
    }

    /**
     * Steps 4-6: Find user, timing-attack protection, verify password.
     *
     * @return User
     * @throws InvalidCredentialsException
     */
    private function findAndVerifyPassword(string $email, string $password, string $ip): User
    {
        $user = $this->authRepository->findByEmail($email);

        // Step 5: User not found / inactive — timing attack protection
        if (!$user || !$user->is_active) {
            Hash::check('dummy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');
            $this->dispatchAudit(null, 'LOGIN_FAILED', ['reason' => 'user_not_found_or_inactive']);
            throw new InvalidCredentialsException();
        }

        // Step 6: Password verify
        $passwordCorrect = Hash::check($password, $user->getAuthPassword());

        if (!$passwordCorrect) {
            $this->recordFailedAttempt($ip, $email, $user->id);
            throw new InvalidCredentialsException();
        }

        return $user;
    }

    /**
     * Record a failed password attempt: atomic INCR + auto-lock after 5.
     */
    private function recordFailedAttempt(string $ip, string $email, int $userId): void
    {
        $attemptsKey = self::REDIS_PREFIX . ":attempts:{$ip}:{$email}";
        $lockKey     = self::REDIS_PREFIX . ":lock:{$ip}:{$email}";

        $result = Redis::eval(
            "local attemptsKey = KEYS[1]\n" .
            "local lockKey     = KEYS[2]\n" .
            "local maxAttempts = tonumber(ARGV[1])\n" .
            "local lockSec     = tonumber(ARGV[2])\n" .
            "local c = redis.call('INCR', attemptsKey)\n" .
            "redis.call('EXPIRE', attemptsKey, 900)\n" .
            "if c >= maxAttempts then\n" .
            "    redis.call('SETEX', lockKey, lockSec, 'locked')\n" .
            "    return {1, c}\n" .
            "end\n" .
            "return {0, c}\n",
            2,
            $attemptsKey,
            $lockKey,
            5,    // max failed attempts before lock
            900   // lock duration: 15 minutes
        );

        $isLocked   = (bool) $result[0];
        $newCount   = (int) $result[1];
        $remaining  = max(0, 5 - $newCount);
        $reqCaptcha = $newCount >= 3;

        // Log failed attempt
        $this->dispatchAudit($userId, 'LOGIN_FAILED', [
            'reason'   => 'wrong_password',
            'attempts' => $newCount,
            'ip'       => $ip,
        ]);

        // Log account lock
        if ($isLocked) {
            $this->logAccountLock($ip, $email, $newCount);
        }

        // Throw with attempt info
        throw new InvalidCredentialsException(
            message: 'Invalid credentials',
            attemptsLeft: $remaining,
            requiresCaptcha: $reqCaptcha
        );
    }

    /**
     * Step 7: Reset failed attempts counter on successful login.
     */
    private function resetAttempts(string $ip, string $email): void
    {
        $attemptsKey = self::REDIS_PREFIX . ":attempts:{$ip}:{$email}";
        Redis::del($attemptsKey);
    }

    // ========================================================================
    // LOGGING BLOCKED / LOCKED ATTEMPTS (Redis lists for monitoring)
    // ========================================================================

    /**
     * Log a rate-limited attempt to Redis list (for monitoring).
     * Email được hash (SHA-256) để bảo vệ PII.
     */
    private function logBlockedAttempt(string $ip, string $email, string $type, int $count): void
    {
        $logKey  = self::REDIS_PREFIX . ":blocked:" . date('Y-m-d');
        $logData = json_encode([
            'timestamp'  => now()->toIso8601String(),
            'ip'         => $ip,
            'email_hash' => hash('sha256', $email),
            'type'       => $type, // 'ip' hoặc 'pair'
            'count'      => $count,
        ]);

        Redis::lpush($logKey, $logData);
        Redis::expire($logKey, 86400 * 7); // Keep 7 days

        // Alert if suspiciously high volume
        if ($count > 50) {
            // TODO: Dispatch alert job (Discord/Slack/Email)
            // AlertHighVolumeLoginAttempts::dispatch($ip, $count);
        }
    }

    /**
     * Log an account lock event to Redis (for security monitoring).
     */
    private function logAccountLock(string $ip, string $email, int $attempts): void
    {
        $logKey  = self::REDIS_PREFIX . ":locks:" . date('Y-m-d');
        $logData = json_encode([
            'timestamp'  => now()->toIso8601String(),
            'ip'         => $ip,
            'email_hash' => hash('sha256', $email),
            'attempts'   => $attempts,
        ]);

        Redis::lpush($logKey, $logData);
        Redis::expire($logKey, 86400 * 30); // Keep 30 days
    }

    // ========================================================================
    // TOKEN OPERATIONS — T1.3 Redis refresh rotation + grace 10-30s + reuse detection
    // ========================================================================

    /**
     * Refresh an access token using the raw refresh token (read from HttpOnly cookie).
     * T1.3: Redis rotation + grace 10-30s (idempotent) + family reuse detection.
     * Lưu user_id/sid/jti/tv, revoke 1 vs all.
     */
    public function refresh(string $rawRefreshToken)
    {
        $ip        = RequestIp::resolve(request());
        $tokenHash = hash('sha256', $rawRefreshToken);
        $rotation  = app(RefreshRotationService::class);

        // Fast-path: check Redis grace (idempotent replay within 10-30s)
        $graceNew = $rotation->getGrace($tokenHash);
        if ($graceNew) {
            $payload = $rotation->getGracePayload($tokenHash);
            if ($payload && isset($payload['raw'])) {
                $newHash = $payload['hash'];
                $newMeta = $rotation->getRefresh($newHash);
                if ($newMeta) {
                    // Load user from new token meta
                    $user = User::find((int) $newMeta['user_id']);
                    if ($user && $user->is_active) {
                        $accessToken = $this->mintAccessToken($user, $newMeta['sid'] ?? null, (int) ($newMeta['tv'] ?? $user->token_version));
                        $this->dispatchAudit($user->id, 'TOKEN_REFRESH_REPLAY', ['ip' => $ip, 'jti' => $newMeta['jti'] ?? null, 'sid' => $newMeta['sid'] ?? null]);
                        return [
                            'user'          => $user->load('profile', 'roles'),
                            'access_token'  => $accessToken,
                            'refresh_token' => $payload['raw'],
                        ];
                    }
                }
            }
        }

        $record = RefreshToken::where('token_hash', $tokenHash)->first();

        // Reuse detection: token already revoked but being presented again OUTSIDE grace
        if ($record && $record->revoked_at !== null) {
            $family = $record->family ?? null;
            if ($family) {
                $rotation->revokeFamily($family, (int) $record->user_id, 'reuse_detected');
            } else {
                // No family column yet → fallback revoke all
                $rotation->revokeAll((int) $record->user_id);
            }
            $this->dispatchAudit($record->user_id, 'REFRESH_TOKEN_REUSE_DETECTED', ['ip' => $ip, 'jti' => $record->jti ?? null, 'sid' => $record->sid ?? null, 'family' => $family]);
            throw new InvalidCredentialsException('Invalid or expired refresh token — reuse detected');
        }

        if (!$record || $record->expires_at->isPast()) {
            throw new InvalidCredentialsException('Invalid or expired refresh token');
        }

        $user = $record->user;
        if (!$user || !$user->is_active) {
            throw new InvalidCredentialsException('Invalid or expired refresh token');
        }

        // Verify tv mismatch (token_version bumped via logoutAll)
        $recordTv = $record->tv ?? (int) $user->token_version;
        if ((int) $recordTv !== (int) $user->token_version) {
            throw new InvalidCredentialsException('Invalid or expired refresh token — token version mismatch');
        }

        // Check family tombstone (already revoked family)
        if (!empty($record->family) && $rotation->isFamilyRevoked($record->family)) {
            throw new InvalidCredentialsException('Invalid or expired refresh token — family revoked');
        }

        // Rotate via service (keeps same family/sid, new jti/tv)
        try {
            $new = $rotation->rotate($tokenHash, $user, $record->device_info, $ip);
        } catch (InvalidCredentialsException $e) {
            throw $e;
        }

        // Mint new access token with sid/jti/tv from new refresh meta
        $accessToken = $this->mintAccessToken($user, $new['sid'], (int) $new['tv']);

        $this->dispatchAudit($user->id, 'TOKEN_REFRESH', ['ip' => $ip, 'jti' => $new['jti'], 'sid' => $new['sid'], 'family' => $new['family']]);

        return [
            'user'          => $user->load('profile', 'roles'),
            'access_token'  => $accessToken,
            'refresh_token' => $new['raw'],
        ];
    }

    private function mintAccessToken(User $user, ?string $sid, int $tv): string
    {
        if (! $user->relationLoaded('roles')) {
            $user->load('roles');
        }
        $roles = $user->roles->pluck('name')->values()->toArray();
        $jti = (string) Str::uuid();
        return auth('api')->claims([
            'jti'   => $jti,
            'sid'   => $sid ?? (string) $user->id,
            'tv'    => $tv,
            'ver'   => $tv,
            'roles' => $roles,
        ])->login($user);
    }

    /**
     * Issue an access token + a rotating refresh token, and persist the session.
     * T1.3: uses RefreshRotationService to persist family/sid/jti/tv + grace handling.
     */
    private function issueTokens(User $user, ?array $deviceInfo, string $ip): array
    {
        $rotation = app(RefreshRotationService::class);
        $result   = $rotation->issue($user, $deviceInfo, $ip);

        $accessToken = $this->mintAccessToken($user, $result['sid'], (int) $result['tv']);

        return [
            'access_token'     => $accessToken,
            'refresh_token'    => $result['raw'],
            'refresh_token_id' => $result['id'],
            'sid'              => $result['sid'],
            'jti'              => $result['jti'],
            'family'           => $result['family'],
            'tv'               => $result['tv'],
        ];
    }

    // ========================================================================
    // LOGOUT
    // ========================================================================

    public function logout($user, ?string $refreshToken = null)
    {
        // T5.1: lưu jti/sid/exp để blacklist jti (TTL = còn lại access) + revoke sid trước khi tymon xoá token
        $jti = null; $sid = null; $exp = null;
        try {
            $payload = auth('api')->getPayload();
            if ($payload) {
                $jti = $payload->get('jti');
                $sid = $payload->get('sid');
                $exp = $payload->get('exp');
            }
        } catch (\Throwable $e) {}

        // Vô hiệu hóa access token hiện tại (blacklist JWT tymon)
        auth('api')->logout();

        // T5.1 hybrid: blacklist jti với TTL còn lại của access token + revoke sid
        try {
            $revoke = app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class);
            if (!empty($jti)) {
                $ttl = \App\Domains\Identity\Services\Auth\TokenRevocationService::remainingTtlFromExp(is_numeric($exp) ? (int) $exp : null);
                $revoke->blacklistJti((string) $jti, $ttl);
            }
            if (!empty($sid)) {
                $revoke->revokeSid((string) $sid);
            }
        } catch (\Throwable $e) {}

        // Thu hồi refresh token (session) của thiết bị hiện tại — revoke 1
        if ($refreshToken) {
            $tokenHash = hash('sha256', $refreshToken);
            $record = RefreshToken::where('token_hash', $tokenHash)->first();
            if ($record) {
                $record->update(['revoked_at' => now()]);
                app(RefreshRotationService::class)->revokeOne($tokenHash, $user?->id);
                // đảm bảo sid của refresh cũng bị revoke (Go sẽ chặn)
                if (!empty($record->sid)) {
                    try { app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class)->revokeSid((string) $record->sid); } catch (\Throwable $e) {}
                }
                UserSession::where('refresh_token_id', $record->id)->delete();
            }
        }

        $this->dispatchAudit($user?->id, 'LOGOUT', []);

        return true;
    }

    /**
     * Đăng xuất khỏi tất cả thiết bị: thu hồi mọi refresh token + bump token_version
     * để vô hiệu hóa ngay lập tức mọi access token đang lưu hành. (revoke all)
     */
    public function logoutAll($user)
    {
        // T5.1: blacklist jti hiện tại trước khi revoke all
        try {
            $payload = auth('api')->getPayload();
            if ($payload) {
                $jti = $payload->get('jti');
                $exp = $payload->get('exp');
                if (!empty($jti)) {
                    $ttl = \App\Domains\Identity\Services\Auth\TokenRevocationService::remainingTtlFromExp(is_numeric($exp) ? (int) $exp : null);
                    app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class)->blacklistJti((string) $jti, $ttl);
                }
            }
        } catch (\Throwable $e) {}

        auth('api')->logout();

        // T1.3: Redis revoke all families + sid + grace
        app(RefreshRotationService::class)->revokeAll((int) $user->id);

        // Raw update để không trigger UserObserver (tránh đệ quy events) + đồng bộ Redis tv
        DB::table('users')
            ->where('id', $user->id)
            ->update(['token_version' => DB::raw('token_version + 1')]);
        try {
            $fresh = \App\Domains\Identity\Models\User::on('identity')->find($user->id);
            $newTv = (int) ($fresh?->token_version ?? 1);
            app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class)->setUserTv((int) $user->id, $newTv);
        } catch (\Throwable $e) {}

        app(PermissionCacheService::class)->clearUser($user->id);

        // T6.1: publish auth.events for Go consumers to invalidate permission cache + idempotency
        try {
            $fresh2 = \App\Domains\Identity\Models\User::on('identity')->find($user->id);
            $newTv2 = (int)($fresh2?->token_version ?? $user->token_version + 1);
            \App\Services\AuthEventPublisher::tokenVersionBumped((int)$user->id, $newTv2, 'logout_all');
            \App\Services\AuthEventPublisher::permissionsChanged((int)$user->id, [], 'logout_all');
        } catch (\Throwable $e) { \Illuminate\Support\Facades\Log::warning('AuthEventPublisher logoutAll failed: '.$e->getMessage()); }

        $this->dispatchAudit($user->id, 'LOGOUT_ALL', []);

        return true;
    }

    // ========================================================================
    // EMAIL VERIFICATION
    // ========================================================================

    public function verifyEmail(string $token)
    {
        $tokenHash = hash('sha256', $token);

        $verification = $this->emailVerificationRepository->findByTokenHash($tokenHash);

        if (!$verification || $verification->expires_at < now()) {
            throw ValidationException::withMessages([
                'token' => 'Token xác minh không hợp lệ hoặc đã hết hạn.'
            ]);
        }

        $this->authRepository->update($verification->user_id, [
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->emailVerificationRepository->markAsVerified($verification->id);

        $this->dispatchAudit($verification->user_id, 'EMAIL_VERIFIED', []);

        return true;
    }

    public function forgotPassword(array $data)
    {
        $status = Password::sendResetLink($data);
        $email = strtolower($data['email'] ?? '');
        if ($status === Password::RESET_LINK_SENT) {
            $this->dispatchAudit(null, 'FORGOT_PASSWORD', ['email_hash' => hash('sha256', $email)]);
        } else {
            $this->dispatchAudit(null, 'FORGOT_PASSWORD_FAILED', ['email_hash' => hash('sha256', $email), 'status' => $status]);
        }
        return $status;
    }

    public function resetPassword(array $data)
    {
        // T5.1: Password::reset -> UserObserver already bumps tv on password change; we suppress observer double-bump
        // by doing the password update without events, then bump once explicitly.
        $status = Password::reset($data, function ($user, $password) {
            \App\Domains\Identity\Models\User::withoutEvents(function () use ($user, $password) {
                $this->userRepository->updateUser($user->id, ['password' => $password]);
            });
        });
        $email = strtolower($data['email'] ?? '');
        if ($status === Password::PASSWORD_RESET) {
            // T5.1: đổi pass → bump tv để revoke toàn bộ access token đang lưu hành
            try {
                $u = $this->authRepository->findByEmail($email);
                if ($u) {
                    app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class)->bumpTokenVersion($u);
                    // revoke all refresh sessions của user này
                    app(\App\Domains\Identity\Services\Auth\RefreshRotationService::class)->revokeAll((int) $u->id);
                }
            } catch (\Throwable $e) {}
            // resolve user for audit (email unique)
            $u = $this->authRepository->findByEmail($email);
            $this->dispatchAudit($u?->id, 'PASSWORD_RESET', ['email_hash' => hash('sha256', $email)]);
        } else {
            $this->dispatchAudit(null, 'PASSWORD_RESET_FAILED', ['email_hash' => hash('sha256', $email), 'status' => $status]);
        }
        return $status;
    }

    /**
     * T5.1: đổi mật khẩu khi đã đăng nhập — bump tv + revoke all
     * Note: hashedPassword already hashed via Hash::make in controller; avoid double-hash by direct DB update withoutEvents.
     */
    public function changePassword(User $user, string $hashedPassword): void
    {
        // Avoid double bump: UserObserver would bump on password change, so suppress events and bump once explicitly.
        \App\Domains\Identity\Models\User::withoutEvents(function () use ($user, $hashedPassword) {
            \Illuminate\Support\Facades\DB::connection('identity')->table('users')
                ->where('id', $user->id)
                ->update(['password' => $hashedPassword]);
        });
        // Refresh model password attribute for consistency
        $user->setAttribute('password', $hashedPassword);
        // bump tv + revoke all refresh → tất cả token cũ vô hiệu
        app(\App\Domains\Identity\Services\Auth\TokenRevocationService::class)->bumpTokenVersion($user);
        app(\App\Domains\Identity\Services\Auth\RefreshRotationService::class)->revokeAll((int) $user->id);
        $this->dispatchAudit($user->id, 'PASSWORD_CHANGED', []);
    }

    // ========================================================================
    // AUDIT LOG
    // ========================================================================

    /**
     * Dispatch audit log bất đồng bộ (Queue Job).
     * Nếu Queue chưa sẵn sàng, fallback về ghi thẳng vào DB.
     */
    private function dispatchAudit(?int $userId, string $action, array $metadata = []): void
    {
        $ip = RequestIp::resolve(request());

        try {
            WriteAuditLog::dispatch(
                $userId,
                $action,
                $ip,
                request()->userAgent(),
                $metadata
            )->onQueue('audit');
        } catch (Exception $e) {
            \App\Domains\Identity\Models\AuditLog::create([
                'user_id'    => $userId,
                'action'     => $action,
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'metadata'   => $metadata,
            ]);
        }
    }
}
