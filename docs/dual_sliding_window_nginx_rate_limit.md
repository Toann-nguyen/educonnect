# Dual Sliding Window Rate Limiter + Nginx Config

> Complete implementation for Laravel + Redis + Nginx rate limiting

---

## PART 1: Dual Sliding Window (PHP + Lua)

### 1.1 AuthService.php — Login Method

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class AuthService
{
    // Rate limit configuration
    private const IP_WINDOW_SECONDS = 60;
    private const IP_MAX_REQUESTS = 30;
    private const PAIR_WINDOW_SECONDS = 60;
    private const PAIR_MAX_REQUESTS = 10;
    private const REDIS_PREFIX = 'educonnect:auth';

    /**
     * Step 1: Dual sliding window rate limit
     * Check both IP-wide and IP+Email pair limits
     */
    public function checkRateLimit(string $ip, string $email): void
    {
        $now = microtime(true);
        $ipKey = sprintf('%s:rate:ip:%s', self::REDIS_PREFIX, $ip);
        $pairKey = sprintf('%s:rate:login:%s:%s', self::REDIS_PREFIX, $ip, $email);

        $ipUniqueId = uniqid('ip_', true);
        $pairUniqueId = uniqid('pair_', true);

        $luaScript = <<<'LUA'
            local ipKey = KEYS[1]
            local pairKey = KEYS[2]
            local now = tonumber(ARGV[1])
            local window = tonumber(ARGV[2])
            local ipLimit = tonumber(ARGV[3])
            local pairLimit = tonumber(ARGV[4])
            local ipUniqId = ARGV[5]
            local pairUniqId = ARGV[6]
            local clearBefore = now - window

            -- Remove old entries from both keys
            redis.call('ZREMRANGEBYSCORE', ipKey, 0, clearBefore)
            redis.call('ZREMRANGEBYSCORE', pairKey, 0, clearBefore)

            local ipCount = redis.call('ZCARD', ipKey)
            local pairCount = redis.call('ZCARD', pairKey)

            -- Check limits
            if ipCount >= ipLimit then
                local oldest = redis.call('ZRANGE', ipKey, 0, 0, 'WITHSCORES')
                local retryAfter = math.ceil(oldest[2] + window - now)
                return {0, 'ip', ipCount, retryAfter}
            end

            if pairCount >= pairLimit then
                local oldest = redis.call('ZRANGE', pairKey, 0, 0, 'WITHSCORES')
                local retryAfter = math.ceil(oldest[2] + window - now)
                return {0, 'pair', pairCount, retryAfter}
            end

            -- Add current request to both keys
            redis.call('ZADD', ipKey, now, ipUniqId)
            redis.call('EXPIRE', ipKey, window)
            redis.call('ZADD', pairKey, now, pairUniqId)
            redis.call('EXPIRE', pairKey, window)

            return {1, 'allowed', ipCount + 1, 0}
        LUA;

        $result = Redis::eval(
            $luaScript,
            2,
            $ipKey,
            $pairKey,
            $now,
            self::IP_WINDOW_SECONDS,
            self::IP_MAX_REQUESTS,
            self::PAIR_MAX_REQUESTS,
            $ipUniqueId,
            $pairUniqueId
        );

        $allowed = (bool) $result[0];

        if (!$allowed) {
            $blockedBy = $result[1]; // 'ip' or 'pair'
            $count = $result[2];
            $retryAfter = $result[3];

            // Log blocked attempt
            $this->logBlockedAttempt($ip, $email, $blockedBy, $count);

            throw new IPSpamException(
                message: sprintf(
                    'Too many login attempts from %s. Retry after %d seconds.',
                    $blockedBy === 'ip' ? 'this IP' : 'this account',
                    $retryAfter
                ),
                code: 429,
                retryAfter: $retryAfter
            );
        }
    }

    /**
     * Log blocked attempts for monitoring
     */
    private function logBlockedAttempt(string $ip, string $email, string $type, int $count): void
    {
        $logKey = sprintf('%s:blocked:%s', self::REDIS_PREFIX, date('Y-m-d'));
        $logEntry = json_encode([
            'timestamp' => now()->toIso8601String(),
            'ip' => $ip,
            'email_hash' => hash('sha256', $email), // Don't log plain email
            'type' => $type,
            'count' => $count,
        ]);

        Redis::lpush($logKey, $logEntry);
        Redis::expire($logKey, 86400 * 7); // Keep 7 days

        // Optional: Send alert if high volume
        if ($count > 50) {
            // Dispatch alert job (Discord/Slack/Email)
            // AlertHighVolumeLoginAttempts::dispatch($ip, $count);
        }
    }

    /**
     * Step 2: Check if account is locked
     */
    public function isAccountLocked(string $ip, string $email): bool
    {
        $lockKey = sprintf('%s:lock:%s:%s', self::REDIS_PREFIX, $ip, $email);
        return (bool) Redis::get($lockKey);
    }

    /**
     * Step 3: Check captcha requirement
     */
    public function requiresCaptcha(string $ip, string $email): bool
    {
        $attemptsKey = sprintf('%s:attempts:%s:%s', self::REDIS_PREFIX, $ip, $email);
        $attempts = (int) Redis::get($attemptsKey);
        return $attempts >= 3;
    }

    /**
     * Step 4: Record failed attempt
     */
    public function recordFailedAttempt(string $ip, string $email): void
    {
        $attemptsKey = sprintf('%s:attempts:%s:%s', self::REDIS_PREFIX, $ip, $email);
        $lockKey = sprintf('%s:lock:%s:%s', self::REDIS_PREFIX, $ip, $email);

        $luaScript = <<<'LUA'
            local attemptsKey = KEYS[1]
            local lockKey = KEYS[2]
            local maxAttempts = tonumber(ARGV[1])
            local lockDuration = tonumber(ARGV[2])

            local attempts = redis.call('INCR', attemptsKey)
            redis.call('EXPIRE', attemptsKey, 900) -- 15 minutes

            if attempts >= maxAttempts then
                redis.call('SET', lockKey, '1', 'EX', lockDuration)
                return {1, attempts} -- locked
            end

            return {0, attempts} -- not locked
        LUA;

        $result = Redis::eval(
            $luaScript,
            2,
            $attemptsKey,
            $lockKey,
            5,     -- max failed attempts before lock
            900    -- lock duration: 15 minutes
        );

        $isLocked = (bool) $result[0];
        $attemptCount = $result[1];

        if ($isLocked) {
            // Log account lock
            $this->logAccountLock($ip, $email, $attemptCount);
        }
    }

    /**
     * Step 5: Reset attempts on successful login
     */
    public function resetAttempts(string $ip, string $email): void
    {
        $attemptsKey = sprintf('%s:attempts:%s:%s', self::REDIS_PREFIX, $ip, $email);
        Redis::del($attemptsKey);
    }

    /**
     * Log account lock for security monitoring
     */
    private function logAccountLock(string $ip, string $email, int $attempts): void
    {
        $logKey = sprintf('%s:locks:%s', self::REDIS_PREFIX, date('Y-m-d'));
        $logEntry = json_encode([
            'timestamp' => now()->toIso8601String(),
            'ip' => $ip,
            'email_hash' => hash('sha256', $email),
            'attempts' => $attempts,
        ]);

        Redis::lpush($logKey, $logEntry);
        Redis::expire($logKey, 86400 * 30); // Keep 30 days
    }

    /**
     * Main login flow
     */
    public function login(Request $request): array
    {
        $ip = $this->getRealIp($request);
        $email = $request->input('email');

        // Step 1: Rate limit
        $this->checkRateLimit($ip, $email);

        // Step 2: Check lock
        if ($this->isAccountLocked($ip, $email)) {
            throw new AccountLockedException('Account temporarily locked. Try again later.', 423);
        }

        // Step 3: Check captcha
        if ($this->requiresCaptcha($ip, $email)) {
            // Verify captcha token from request
            if (!$this->verifyCaptcha($request->input('captcha_token'))) {
                throw new CaptchaRequiredException('Captcha verification required.', 403);
            }
        }

        // Step 4: Authenticate
        $credentials = $request->only('email', 'password');

        if (!auth()->attempt($credentials)) {
            $this->recordFailedAttempt($ip, $email);
            throw new UnauthorizedException('Invalid credentials.', 401);
        }

        // Step 5: Success — reset attempts
        $this->resetAttempts($ip, $email);

        // Generate tokens
        $user = auth()->user();
        $tokens = $this->generateTokens($user);

        return [
            'message' => 'Login successful!',
            'access_token' => $tokens['access'],
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'refresh_token' => $tokens['refresh'], // Or set as HttpOnly cookie
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'roles' => $this->getUserRoles($user),
            ],
        ];
    }

    /**
     * Get real client IP with priority chain
     */
    private function getRealIp(Request $request): string
    {
        // Priority 1: Cloudflare
        $cfIp = $request->header('CF-Connecting-IP');
        if ($cfIp && filter_var($cfIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $cfIp;
        }

        // Priority 2: X-Forwarded-For (first IP)
        $xff = $request->header('X-Forwarded-For');
        if ($xff) {
            $ips = array_map('trim', explode(',', $xff));
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Priority 3: Remote address
        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * Generate JWT tokens
     */
    private function generateTokens($user): array
    {
        $accessToken = auth()->claims([
            'type' => 'access',
            'ver' => $user->permissions_version ?? 1,
        ])->tokenById($user->id);

        $refreshToken = uniqid('refresh_', true) . bin2hex(random_bytes(16));

        // Store refresh token in Redis
        $refreshKey = sprintf('%s:refresh:%d:%s', self::REDIS_PREFIX, $user->id, hash('sha256', $refreshToken));
        Redis::setex($refreshKey, 604800, json_encode([ // 7 days
            'created_at' => now()->toIso8601String(),
            'ip' => $this->getRealIp(request()),
            'user_agent' => substr(request()->userAgent() ?? '', 0, 255),
        ]));

        return [
            'access' => $accessToken,
            'refresh' => $refreshToken,
        ];
    }

    /**
     * Get user roles (from cache or DB)
     */
    private function getUserRoles($user): array
    {
        return app(PermissionCacheService::class)->getRoles($user->id);
    }

    /**
     * Verify captcha token (Google reCAPTCHA or hCaptcha)
     */
    private function verifyCaptcha(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $token,
            'remoteip' => $this->getRealIp(request()),
        ]);

        return $response->json('success', false);
    }
}
```

### 1.2 Custom Exceptions

```php
<?php

namespace App\Exceptions;

class IPSpamException extends \Exception
{
    public int $retryAfter;

    public function __construct(string $message = '', int $code = 429, int $retryAfter = 60)
    {
        parent::__construct($message, $code);
        $this->retryAfter = $retryAfter;
    }

    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'retry_after' => $this->retryAfter,
        ], $this->getCode())->withHeaders([
            'Retry-After' => $this->retryAfter,
            'X-RateLimit-Limit' => $this->getCode() === 429 ? 10 : 30,
            'X-RateLimit-Remaining' => 0,
        ]);
    }
}

class AccountLockedException extends \Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'locked' => true,
            'retry_after' => 900, // 15 minutes
        ], 423)->withHeaders([
            'Retry-After' => 900,
        ]);
    }
}

class CaptchaRequiredException extends \Exception
{
    public function render($request)
    {
        return response()->json([
            'message' => $this->getMessage(),
            'requires_captcha' => true,
        ], 403);
    }
}
```

### 1.3 Rate Limit Service for Other Endpoints

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Http\Request;

class RateLimitService
{
    private const REDIS_PREFIX = 'educonnect:ratelimit';

    /**
     * Generic sliding window rate limit
     */
    public function check(
        string $identifier,
        int $maxAttempts,
        int $windowSeconds,
        string $type = 'generic'
    ): array {
        $key = sprintf('%s:%s:%s', self::REDIS_PREFIX, $type, md5($identifier));
        $now = microtime(true);
        $windowStart = $now - $windowSeconds;
        $member = uniqid('', true);

        $luaScript = <<<'LUA'
            local key = KEYS[1]
            local now = tonumber(ARGV[1])
            local windowStart = tonumber(ARGV[2])
            local maxAttempts = tonumber(ARGV[3])
            local windowSeconds = tonumber(ARGV[4])

            redis.call('ZREMRANGEBYSCORE', key, 0, windowStart)
            local count = redis.call('ZCARD', key)

            if count >= maxAttempts then
                local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')
                local retryAfter = math.ceil(oldest[2] + windowSeconds - now)
                return {0, count, retryAfter}
            end

            redis.call('ZADD', key, now, ARGV[5])
            redis.call('EXPIRE', key, windowSeconds)

            return {1, count + 1, 0}
        LUA;

        $result = Redis::eval(
            $luaScript,
            1,
            $key,
            $now,
            $windowStart,
            $maxAttempts,
            $windowSeconds,
            $member
        );

        return [
            'allowed' => (bool) $result[0],
            'remaining' => max(0, $maxAttempts - $result[1]),
            'retry_after' => $result[2],
        ];
    }

    /**
     * Check by request with auto IP extraction
     */
    public function checkRequest(
        Request $request,
        string $type,
        int $maxAttempts,
        int $windowSeconds
    ): array {
        $identifier = $this->getIdentifier($request, $type);
        return $this->check($identifier, $maxAttempts, $windowSeconds, $type);
    }

    /**
     * Get rate limit identifier based on type
     */
    private function getIdentifier(Request $request, string $type): string
    {
        $ip = $this->getRealIp($request);

        return match ($type) {
            'login' => "ip:{$ip}",
            'register' => "ip:{$ip}",
            'refresh' => "ip:{$ip}:" . ($request->cookie('refresh_token') ? 'cookie' : 'none'),
            'forgot_password' => "email:" . $request->input('email', 'unknown'),
            'reset_password' => "ip:{$ip}",
            'api' => $request->user() ? "user:{$request->user()->id}" : "ip:{$ip}",
            default => "ip:{$ip}",
        };
    }

    /**
     * Get real client IP
     */
    private function getRealIp(Request $request): string
    {
        $cfIp = $request->header('CF-Connecting-IP');
        if ($cfIp && filter_var($cfIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $cfIp;
        }

        $xff = $request->header('X-Forwarded-For');
        if ($xff) {
            $ips = array_map('trim', explode(',', $xff));
            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * Reset rate limit for identifier
     */
    public function reset(string $identifier, string $type = 'generic'): void
    {
        $key = sprintf('%s:%s:%s', self::REDIS_PREFIX, $type, md5($identifier));
        Redis::del($key);
    }
}
```

---

## PART 2: Nginx Rate Limit Config

### 2.1 Main Rate Limit Config

```nginx
# /home/robert/production/nginx/conf.d/rate-limit.conf

# ============================================
# REAL IP CONFIGURATION (Cloudflare + Docker)
# ============================================

# Trust Cloudflare IP ranges
set_real_ip_from 172.16.0.0/12;   # Docker internal
set_real_ip_from 10.0.0.0/8;      # Private network
set_real_ip_from 103.21.244.0/22; # Cloudflare
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 131.0.72.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;

# Priority: CF-Connecting-IP > X-Forwarded-For > $remote_addr
real_ip_header CF-Connecting-IP;
real_ip_recursive on;

# ============================================
# RATE LIMIT ZONES
# ============================================

# Zone 1: Login - Strict (30r/m per IP)
limit_req_zone $binary_remote_addr zone=login:10m rate=30r/m;

# Zone 2: Auth endpoints - Strict (10r/m per IP)
limit_req_zone $binary_remote_addr zone=auth:10m rate=10r/m;

# Zone 3: General API - Moderate (60r/m per IP)
limit_req_zone $binary_remote_addr zone=api:10m rate=60r/m;

# Zone 4: Static/Next.js - Lenient (100r/m per IP)
limit_req_zone $binary_remote_addr zone=static:10m rate=100r/m;

# ============================================
# LOG FORMAT (for debugging)
# ============================================

log_format rate_limit '$realip_remote_addr - $remote_addr [$time_local] '
                      '"$request" $status $body_bytes_sent '
                      'rt=$request_time '
                      'cf_ip="$http_cf_connecting_ip" '
                      'xff="$http_x_forwarded_for" '
                      'limit=$limit_req_status';

access_log /var/log/nginx/access.log rate_limit;
```

### 2.2 API Server Block

```nginx
# /home/robert/production/nginx/conf.d/api.toanrobert.online.conf

server {
    listen 80;
    server_name api.toanrobert.online;

    client_max_body_size 100M;
    client_body_timeout 60s;
    client_header_timeout 60s;

    # ============================================
    # SECURITY HEADERS
    # ============================================
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    # ============================================
    # AUTH ENDPOINTS — STRICT RATE LIMITING
    # ============================================

    # Login: 30r/m per IP, burst 5
    location = /api/auth/login {
        limit_req zone=login burst=5 nodelay;
        limit_req_status 429;
        limit_req_log_level warn;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;

        # Timeouts
        proxy_connect_timeout 30s;
        proxy_send_timeout 30s;
        proxy_read_timeout 30s;
    }

    # Register: 10r/m per IP, burst 3
    location = /api/auth/register {
        limit_req zone=auth burst=3 nodelay;
        limit_req_status 429;
        limit_req_log_level warn;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;
    }

    # Refresh token: 10r/m per IP, burst 10
    location = /api/auth/refresh {
        limit_req zone=auth burst=10 nodelay;
        limit_req_status 429;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;
    }

    # Forgot password: 10r/m per IP, burst 3
    location = /api/auth/forgot-password {
        limit_req zone=auth burst=3 nodelay;
        limit_req_status 429;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;
    }

    # Reset password: 10r/m per IP, burst 3
    location = /api/auth/reset-password {
        limit_req zone=auth burst=3 nodelay;
        limit_req_status 429;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;
    }

    # Email verification: 10r/m per IP, burst 5
    location = /api/auth/email/verify {
        limit_req zone=auth burst=5 nodelay;
        limit_req_status 429;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;
    }

    # Resend verification: 10r/m per IP, burst 3
    location = /api/auth/email/resend {
        limit_req zone=auth burst=3 nodelay;
        limit_req_status 429;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;
    }

    # ============================================
    # PROTECTED API — MODERATE RATE LIMITING
    # ============================================

    location /api/ {
        limit_req zone=api burst=20 nodelay;
        limit_req_status 429;

        proxy_pass http://app:9000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;

        # WebSocket support (if needed)
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    # ============================================
    # HEALTH CHECK — NO RATE LIMIT
    # ============================================

    location = /health {
        access_log off;
        return 200 '{"status":"ok"}';
        add_header Content-Type application/json;
    }

    # ============================================
    # ERROR PAGES
    # ============================================

    # Custom 429 response
    error_page 429 @rate_limit_exceeded;
    location @rate_limit_exceeded {
        default_type application/json;
        add_header Retry-After 60 always;
        return 429 '{"message":"Too many requests. Please slow down.","retry_after":60}';
    }

    # Block hidden files
    location ~ /\.(?!well-known) {
        deny all;
        return 404;
    }
}
```

### 2.3 Next.js Frontend Server Block

```nginx
# /home/robert/production/nginx/conf.d/toanrobert.online.conf

server {
    listen 80;
    server_name toanrobert.online www.toanrobert.online;

    client_max_body_size 100M;

    # Security headers
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;

    # Rate limit for SSR requests
    limit_req zone=static burst=50 nodelay;
    limit_req_status 429;

    location / {
        proxy_pass http://nextjs_app:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;

        # Real IP
        proxy_set_header X-Real-IP $realip_remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $http_x_forwarded_proto;
        proxy_set_header CF-Connecting-IP $http_cf_connecting_ip;

        # Timeouts for SSR
        proxy_connect_timeout 30s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # Static assets — cache aggressively
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        proxy_pass http://nextjs_app:3000;
    }

    # Custom 429
    error_page 429 @rate_limit_exceeded;
    location @rate_limit_exceeded {
        default_type application/json;
        add_header Retry-After 60 always;
        return 429 '{"message":"Too many requests.","retry_after":60}';
    }
}
```

---

## PART 3: Laravel Middleware Integration

### 3.1 Rate Limit Middleware

```php
<?php

namespace App\Http\Middleware;

use App\Services\RateLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function __construct(
        private RateLimitService $rateLimiter
    ) {}

    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        $config = match ($type) {
            'login' => ['max' => 10, 'window' => 60, 'name' => 'login'],
            'register' => ['max' => 3, 'window' => 3600, 'name' => 'register'],
            'refresh' => ['max' => 10, 'window' => 60, 'name' => 'refresh'],
            'forgot_password' => ['max' => 3, 'window' => 3600, 'name' => 'forgot_password'],
            'reset_password' => ['max' => 3, 'window' => 3600, 'name' => 'reset_password'],
            'api' => ['max' => 60, 'window' => 60, 'name' => 'api'],
            'sensitive' => ['max' => 10, 'window' => 300, 'name' => 'sensitive'],
            default => ['max' => 100, 'window' => 60, 'name' => 'default'],
        };

        $result = $this->rateLimiter->checkRequest(
            $request,
            $config['name'],
            $config['max'],
            $config['window']
        );

        $response = $next($request);

        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $config['max']);
        $response->headers->set('X-RateLimit-Remaining', $result['remaining']);

        if (!$result['allowed']) {
            return response()->json([
                'message' => 'Too many requests.',
                'retry_after' => $result['retry_after'],
            ], 429)->withHeaders([
                'Retry-After' => $result['retry_after'],
                'X-RateLimit-Limit' => $config['max'],
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        return $response;
    }
}
```

### 3.2 Routes with Rate Limiting

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Public routes with strict rate limiting
Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('rate.limit:login');

Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware('rate.limit:register');

Route::post('/auth/refresh', [AuthController::class, 'refresh'])
    ->middleware('rate.limit:refresh');

Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('rate.limit:forgot_password');

Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('rate.limit:reset_password');

Route::get('/auth/email/verify', [AuthController::class, 'verifyEmail'])
    ->middleware('rate.limit:api');

Route::post('/auth/email/resend', [AuthController::class, 'resendVerification'])
    ->middleware('rate.limit:forgot_password');

// Protected routes
Route::middleware(['auth:api', 'rate.limit:api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout/all', [AuthController::class, 'logoutAll']);
});
```

### 3.3 Kernel Registration

```php
<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected $routeMiddleware = [
        // ... existing middleware ...
        'rate.limit' => \App\Http\Middleware\RateLimitMiddleware::class,
        'auth.jwt' => \App\Http\Middleware\JWTAuthWithPermissions::class,
    ];
}
```

---

## PART 4: Deployment Checklist

### 4.1 Test Nginx Config

```bash
# SSH vào Arch Linux server
ssh user@your-server-ip

# Test Nginx config syntax
docker exec nginx_proxy nginx -t

# Reload Nginx (không downtime)
docker exec nginx_proxy nginx -s reload

# Check logs
docker exec nginx_proxy tail -f /var/log/nginx/access.log | grep 429
docker exec nginx_proxy tail -f /var/log/nginx/error.log
```

### 4.2 Test Redis Rate Limiting

```bash
# Check Redis keys
docker exec redis redis-cli -a AgriRedis2026! KEYS 'educonnect:rate:*'

# Check specific IP
docker exec redis redis-cli -a AgriRedis2026! ZRANGE 'educonnect:rate:ip:1.2.3.4' 0 -1 WITHSCORES

# Check login pair
docker exec redis redis-cli -a AgriRedis2026! ZRANGE 'educonnect:rate:login:1.2.3.4:user@example.com' 0 -1 WITHSCORES

# Check blocked attempts log
docker exec redis redis-cli -a AgriRedis2026! LLEN 'educonnect:auth:blocked:2026-06-20'
```

### 4.3 Test with curl

```bash
# Test login rate limit (should block after 10 requests in 1 minute)
for i in {1..15}; do
  curl -X POST https://api.toanrobert.online/api/auth/login     -H "Content-Type: application/json"     -d '{"email":"test@example.com","password":"wrong"}'     -w "\nHTTP Status: %{http_code}\n" 2>/dev/null | tail -1
done

# Check headers
curl -X POST https://api.toanrobert.online/api/auth/login   -H "Content-Type: application/json"   -d '{"email":"test@example.com","password":"wrong"}'   -I 2>/dev/null | grep -i "x-ratelimit"
```

---

## Summary

| Layer | Implementation | Scope |
|-------|---------------|-------|
| **Nginx** | `limit_req` zones + real IP | All requests, DDoS protection |
| **Laravel Middleware** | Sliding window Redis Lua | Per-endpoint, granular |
| **AuthService** | Dual sliding window (IP + IP:Email) | Login specific, brute-force |
| **Redis** | ZSET + Lua atomic operations | Shared state, distributed |
