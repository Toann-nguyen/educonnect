<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheUserData
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $user = $request->user();

            // Cache đơn giản cho user data
            $cacheKey = "user_data_{$user->id}";
            $userData = Cache::remember($cacheKey, 3600, function () use ($user) {
                return [
                    'roles' => $user->getRoleNames()->toArray(),
                    'permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
                ];
            });

            // Attach to request for controllers
            $request->attributes->set('cached_user_data', $userData);
        }

        return $next($request);
    }
}
