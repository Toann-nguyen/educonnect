<?php

namespace App\Support;

use Illuminate\Http\Request;

class RequestIp
{
    /**
     * Resolve the real client IP using a 3-layer fallback chain suitable for
     * traffic passing through Cloudflare tunnel + nginx + Docker:
     *
     *   1. CF-Connecting-IP  (Cloudflare native, most trusted)
     *   2. First PUBLIC IP in X-Forwarded-For
     *   3. Request::ip()     (remote_addr, last resort)
     *
     * This prevents every user from sharing the proxy IP (which would make
     * IP-based rate limiting / account locking affect all users at once).
     */
    public static function resolve(Request $request): string
    {
        $cf = $request->headers->get('CF-Connecting-IP');
        if ($cf && self::isPublic($cf)) {
            return $cf;
        }

        $xff = $request->headers->get('X-Forwarded-For');
        if ($xff) {
            foreach (explode(',', $xff) as $candidate) {
                $candidate = trim($candidate);
                if (self::isPublic($candidate)) {
                    return $candidate;
                }
            }
        }

        return $request->ip() ?? '0.0.0.0';
    }

    /**
     * A valid, non-private, non-reserved IP address.
     */
    private static function isPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }
}
