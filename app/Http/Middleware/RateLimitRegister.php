<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitRegister
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Sử dụng rate limiter 'register' đã được định nghĩa trong RouteServiceProvider
        // Giới hạn 5 requests/phút/IP cho endpoint register
        return RateLimiter::using('register', function () use ($request, $next) {
            return $next($request);
        });
    }
}
