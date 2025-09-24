<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiAccess
{
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
                'code' => 'UNAUTHENTICATED'
            ], 401);
        }

        $user = $request->user();

        if ($user->trashed()) {
            return response()->json([
                'message' => 'Account has been deactivated.',
                'code' => 'ACCOUNT_DEACTIVATED'
            ], 403);
        }

        if ($user->roles->isEmpty()) {
            return response()->json([
                'message' => 'No roles assigned to this account.',
                'code' => 'NO_ROLES_ASSIGNED'
            ], 403);
        }

        return $next($request);
    }
}
