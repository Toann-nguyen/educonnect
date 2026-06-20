<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use App\Services\Interface\AuthServiceInterface;
use App\Support\RequestIp;
use App\Exceptions\Auth\IPSpamException;
use App\Exceptions\Auth\AccountLockedException;
use App\Exceptions\Auth\CaptchaRequiredException;
use App\Exceptions\Auth\InvalidCredentialsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Exception;
use App\Jobs\SendVerificationEmail;
use App\Jobs\WriteAuditLog;
use App\Models\RefreshToken;
use App\Models\UserSession;
use App\Models\User;

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

        // 3. Tạo token xác minh email
        $rawToken = Str::random(60);
        $tokenHash = hash('sha256', $rawToken);

        $this->emailVerificationRepository->upsert(
            $user->id,
            $tokenHash,
            now()->addHours(24)
        );

        // 4. Gửi email xác minh qua Queue Job
        SendVerificationEmail::dispatch($user, $rawToken);

        // 5. Tự động tạo Access Token (Auto-login)
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
            $preAuthToken = auth('api')->claims(['pre_auth' => true])->setTTL(5)->fromUser($user);
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
    // TOKEN OPERATIONS
    // ========================================================================

    /**
     * Refresh an access token using the raw refresh token (read from HttpOnly cookie).
     * Implements refresh-token rotation + theft detection.
     */
    public function refresh(string $rawRefreshToken)
    {
        $ip        = RequestIp::resolve(request());
        $tokenHash = hash('sha256', $rawRefreshToken);

        $record = RefreshToken::where('token_hash', $tokenHash)->first();

        // Theft detection: a revoked token being reused means it may have been stolen.
        // Revoke ALL of that user's sessions defensively.
        if ($record && $record->revoked_at !== null) {
            RefreshToken::where('user_id', $record->user_id)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);
            UserSession::where('user_id', $record->user_id)->delete();
            $this->dispatchAudit($record->user_id, 'REFRESH_TOKEN_REUSE_DETECTED', ['ip' => $ip]);
            throw new InvalidCredentialsException('Invalid or expired refresh token');
        }

        if (!$record || $record->expires_at->isPast()) {
            throw new InvalidCredentialsException('Invalid or expired refresh token');
        }

        $user = $record->user;
        if (!$user || !$user->is_active) {
            throw new InvalidCredentialsException('Invalid or expired refresh token');
        }

        // Rotate: revoke the old token + drop its cached session, then issue a fresh pair.
        $record->update(['revoked_at' => now(), 'last_used_at' => now()]);
        Redis::del(self::REDIS_PREFIX . ":session:{$tokenHash}");
        UserSession::where('refresh_token_id', $record->id)->delete();

        $tokens = $this->issueTokens($user, $record->device_info, $ip);

        $this->dispatchAudit($user->id, 'TOKEN_REFRESH', ['ip' => $ip]);

        return [
            'user'          => $user->load('profile', 'roles'),
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ];
    }

    /**
     * Issue an access token + a rotating refresh token, and persist the session.
     */
    private function issueTokens(User $user, ?array $deviceInfo, string $ip): array
    {
        $accessToken = auth('api')->login($user);

        $rawRefreshToken = Str::random(60);
        $tokenHash       = hash('sha256', $rawRefreshToken);

        // Device fingerprinting: ưu tiên client-side hash, fallback server-side
        $deviceFingerprint = $deviceInfo['fingerprint'] ?? hash('sha256', request()->userAgent() . '|' . $ip);

        $refreshToken = RefreshToken::create([
            'user_id'            => $user->id,
            'token_hash'         => $tokenHash,
            'device_info'        => $deviceInfo ?? ['user_agent' => request()->userAgent()],
            'device_fingerprint' => $deviceFingerprint,
            'ip_address'         => $ip,
            'expires_at'         => now()->addDays(7),
        ]);

        UserSession::create([
            'user_id'          => $user->id,
            'refresh_token_id' => $refreshToken->id,
            'device_name'      => $deviceInfo['device_name'] ?? 'Unknown Device',
            'ip_address'       => $ip,
            'user_agent'       => request()->userAgent(),
            'last_active_at'   => now(),
        ]);

        // Lưu metadata vào Redis để verify nhanh khi refresh token
        $redisSessionKey = self::REDIS_PREFIX . ":session:{$tokenHash}";
        Redis::hmset($redisSessionKey, [
            'user_id'            => $user->id,
            'device_fingerprint' => $deviceFingerprint,
            'ip_address'         => $ip,
            'expires_at'         => now()->addDays(7)->getTimestamp(),
            'user_agent'         => request()->userAgent(),
        ]);
        Redis::expire($redisSessionKey, 60 * 60 * 24 * 7); // TTL 7 ngày

        return [
            'access_token'     => $accessToken,
            'refresh_token'    => $rawRefreshToken,
            'refresh_token_id' => $refreshToken->id,
        ];
    }

    // ========================================================================
    // LOGOUT
    // ========================================================================

    public function logout($user, ?string $refreshToken = null)
    {
        // Vô hiệu hóa access token hiện tại (blacklist JWT)
        auth('api')->logout();

        // Thu hồi refresh token (session) của thiết bị hiện tại
        if ($refreshToken) {
            $tokenHash = hash('sha256', $refreshToken);
            $record = RefreshToken::where('token_hash', $tokenHash)->first();
            if ($record) {
                $record->update(['revoked_at' => now()]);
                Redis::del(self::REDIS_PREFIX . ":session:{$tokenHash}");
                UserSession::where('refresh_token_id', $record->id)->delete();
            }
        }

        $this->dispatchAudit($user?->id, 'LOGOUT', []);

        return true;
    }

    /**
     * Đăng xuất khỏi tất cả thiết bị: thu hồi mọi refresh token + bump token_version
     * để vô hiệu hóa ngay lập tức mọi access token đang lưu hành.
     */
    public function logoutAll($user)
    {
        auth('api')->logout();

        $tokens = RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->get();

        foreach ($tokens as $token) {
            Redis::del(self::REDIS_PREFIX . ":session:{$token->token_hash}");
        }

        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        UserSession::where('user_id', $user->id)->delete();

        // Raw update để không trigger UserObserver (tránh đệ quy events)
        DB::table('users')
            ->where('id', $user->id)
            ->update(['token_version' => DB::raw('token_version + 1')]);

        app(PermissionCacheService::class)->clearUser($user->id);

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

        return true;
    }

    public function forgotPassword(array $data)
    {
        return Password::sendResetLink($data);
    }

    public function resetPassword(array $data)
    {
        return Password::reset($data, function ($user, $password) {
            $this->userRepository->updateUser($user->id, ['password' => $password]);
        });
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
            \App\Models\AuditLog::create([
                'user_id'    => $userId,
                'action'     => $action,
                'ip_address' => $ip,
                'user_agent' => request()->userAgent(),
                'metadata'   => $metadata,
            ]);
        }
    }
}
