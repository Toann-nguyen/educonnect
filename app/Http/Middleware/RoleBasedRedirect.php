<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleBasedRedirect
{
    private array $roleRedirects = [
        'admin' => '/admin/dashboard',
        'teacher' => '/teacher/dashboard',
        'student' => '/student/dashboard',
        'parent' => '/parent/dashboard',
        'accountant' => '/accountant/dashboard',
        'librarian' => '/librarian/dashboard',
    ];
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'redirect' => '/login'
            ], 401);
        }

        $user = $request->user();

        if (in_array($request->path(), ['/', 'dashboard', 'api/dashboard'])) {
            $redirectPath = $this->getRedirectPath($user);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Redirecting to role-based dashboard',
                    'redirect' => $redirectPath
                ], 302);
            }
        }

        return $next($request);
    }

    private function getRedirectPath($user): string
    {
        foreach ($this->roleRedirects as $role => $path) {
            if ($user->hasRole($role)) {
                return $path;
            }
        }

        return '/dashboard';
    }
}
