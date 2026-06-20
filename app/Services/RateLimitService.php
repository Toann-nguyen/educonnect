<?php

namespace App\Services;

use App\Support\RequestIp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Generic sliding window rate limiter using Redis Sorted Sets (ZSET) + Lua.
 *
 * Supports per-IP, per-user, per-email, or custom identifier rate limiting.
 * All keys are namespaced under 'educonnect:ratelimit:' prefix.
 */
class RateLimitService
{
    private const REDIS_PREFIX = 'educonnect:ratelimit';

    /**
     * Check rate limit for a custom identifier.
     *
     * @param string $identifier  e.g. "ip:1.2.3.4", "user:42", "email:user@example.com"
     * @param int    $maxAttempts Maximum requests allowed in the window
     * @param int    $windowSec   Window duration in seconds
     * @param string $type        Rate limit type (login, register, api, etc.)
     * @return array ['allowed' => bool, 'remaining' => int, 'retry_after' => int]
     */
    public function check(
        string $identifier,
        int $maxAttempts,
        int $windowSec,
        string $type = 'generic'
    ): array {
        $key    = self::REDIS_PREFIX . ":{$type}:" . md5($identifier);
        $now    = microtime(true);
        $windowStart = $now - $windowSec;
        $member = (string) Str::uuid();

        $result = Redis::eval(
            "local key = KEYS[1]\n" .
            "local now = tonumber(ARGV[1])\n" .
            "local windowStart = tonumber(ARGV[2])\n" .
            "local maxAttempts = tonumber(ARGV[3])\n" .
            "local windowSec = tonumber(ARGV[4])\n" .
            "redis.call('ZREMRANGEBYSCORE', key, '-inf', windowStart)\n" .
            "local count = redis.call('ZCARD', key)\n" .
            "if count >= maxAttempts then\n" .
            "    local oldest = redis.call('ZRANGE', key, 0, 0, 'WITHSCORES')\n" .
            "    local retryAfter = math.ceil(oldest[2] + windowSec - now)\n" .
            "    return {0, count, retryAfter}\n" .
            "end\n" .
            "redis.call('ZADD', key, now, ARGV[5])\n" .
            "redis.call('EXPIRE', key, windowSec)\n" .
            "return {1, count + 1, 0}\n",
            1,
            $key,
            $now,
            $windowStart,
            $maxAttempts,
            $windowSec,
            $member
        );

        return [
            'allowed'     => (bool) $result[0],
            'remaining'   => max(0, $maxAttempts - $result[1]),
            'retry_after' => $result[2],
        ];
    }

    /**
     * Convenience: check rate limit by Request, auto-resolves identifier.
     */
    public function checkRequest(
        Request $request,
        string $type,
        int $maxAttempts,
        int $windowSec
    ): array {
        $identifier = $this->resolveIdentifier($request, $type);
        return $this->check($identifier, $maxAttempts, $windowSec, $type);
    }

    /**
     * Resolve rate limit identifier based on endpoint type.
     */
    private function resolveIdentifier(Request $request, string $type): string
    {
        $ip = RequestIp::resolve($request);

        return match ($type) {
            'login'          => "ip:{$ip}",
            'register'       => "ip:{$ip}",
            'refresh'        => "ip:{$ip}:" . ($request->cookie('refresh_token') ? 'has_cookie' : 'no_cookie'),
            'forgot_password' => "email:" . md5(strtolower($request->input('email', 'unknown'))),
            'reset_password'  => "ip:{$ip}",
            'api'            => $request->user() ? "user:{$request->user()->id}" : "ip:{$ip}",
            'sensitive'      => $request->user() ? "user:{$request->user()->id}" : "ip:{$ip}",
            default          => "ip:{$ip}",
        };
    }

    /**
     * Reset rate limit for a given identifier (e.g. on successful login).
     */
    public function reset(string $identifier, string $type = 'generic'): void
    {
        $key = self::REDIS_PREFIX . ":{$type}:" . md5($identifier);
        Redis::del($key);
    }
}
