<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class CheckRoleAndPermission
{
    /**
     * Handle an incoming request.
     * Checks:
     * 1) Authentication (user must exist)
     * 2) Token version (ver/tv claim vs user.token_version) — revocation check
     * 3) Role/Permission (Spatie OR/AND logic)
     *
     * Usage: ->middleware('rbac:principal|homeroom') or 'rbac:manage_users'
     * Supports '|' = OR, '&' = AND within a single segment.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$rolesOrPermissions): Response
    {
        if (!$request->user()) {
            throw new AuthenticationException();
        }

        $user = $request->user();

        // ── Token version check (stateless revocation) ──────────────
        // If request carries a JWT, compare ver/tv claim vs DB token_version.
        // Mismatch => token revoked (logoutAll / password change).
        try {
            $token = JWTAuth::getToken();
            // Fallback: try parse from header if not already set by JwtMiddleware
            if (!$token) {
                try { $token = JWTAuth::parseToken(); } catch (JWTException) { $token = null; }
            }
            if ($token) {
                $payload = JWTAuth::getPayload($token) ?: JWTAuth::parseToken()->getPayload();
                $ver = $payload->get('ver');
                if ($ver === null) $ver = $payload->get('tv'); // alternate claim name
                if ($ver !== null && isset($user->token_version) && (int) $ver !== (int) $user->token_version) {
                    return response()->json(['message' => 'Token has been revoked'], 401);
                }
            }
        } catch (\Throwable) {
            // If JWT not present / invalid, let auth middleware handle; don't block rbac-only checks
        }

        // If no role/permission requirement, pass through (auth + version only)
        if (empty($rolesOrPermissions)) {
            return $next($request);
        }

        foreach ($rolesOrPermissions as $requirementString) {
            $requirementString = trim($requirementString);
            if ($requirementString === '') continue;

            if (str_contains($requirementString, '&')) {
                $andRequirements = array_map('trim', explode('&', $requirementString));
                $andRequirements = array_filter($andRequirements);
                // hasAllRoles/hasAllPermissions checks exact name; try both role and permission
                if ($user->hasAllRoles($andRequirements) || $user->hasAllPermissions($andRequirements)) {
                    return $next($request);
                }
                // Mixed check: user must have ALL items either as role or permission
                $allOk = true;
                foreach ($andRequirements as $item) {
                    if (!$user->hasRole($item) && !$user->can($item)) { $allOk = false; break; }
                }
                if ($allOk) return $next($request);
            } else {
                // OR logic — Spatie handles '|' separator internally
                if ($user->hasAnyRole($requirementString) || $user->hasAnyPermission($requirementString)) {
                    return $next($request);
                }
                // Fallback: mixed role-or-permission via can()
                $orParts = array_map('trim', explode('|', $requirementString));
                foreach ($orParts as $part) {
                    if ($user->hasRole($part) || $user->can($part)) {
                        return $next($request);
                    }
                }
            }
        }

        throw new AuthorizationException('This action is unauthorized.');
    }
}
