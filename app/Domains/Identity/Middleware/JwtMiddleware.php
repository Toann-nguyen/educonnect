<?php

namespace App\Domains\Identity\Middleware;

use App\Domains\Identity\Services\Auth\TokenRevocationService;
use App\Domains\Identity\Services\PermissionCacheService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = JWTAuth::parseToken();
            $user = $token->authenticate();
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }

            $payload = $token->getPayload();

            // Chặn pre-auth token (chỉ dùng cho bước nhập 2FA) bị dùng như access token đầy đủ.
            if ($payload->get('pre_auth')) {
                return response()->json(['message' => '2FA verification required'], 401);
            }

            // T5.1 hybrid revoke: jti blacklist + sid revoked + tv check (Redis trước, DB fallback)
            $revoke = app(TokenRevocationService::class);

            $jti = (string) ($payload->get('jti') ?? '');
            if ($jti !== '' && $revoke->isJtiBlacklisted($jti)) {
                return response()->json(['message' => 'Token has been revoked (jti blacklisted)'], 401);
            }

            $sid = (string) ($payload->get('sid') ?? '');
            if ($sid !== '' && $revoke->isSidRevoked($sid)) {
                return response()->json(['message' => 'Token has been revoked (session revoked)'], 401);
            }

            $tokenVersion = $payload->get('tv', $payload->get('ver'));
            if ($tokenVersion !== null) {
                // Redis fast-path: nếu có cache tv mà lệch → revoked
                if ($revoke->isTvStale((int) $user->id, (int) $tokenVersion)) {
                    return response()->json(['message' => 'Token has been revoked'], 401);
                }
                // DB fallback (source of truth)
                if ((int) $tokenVersion !== (int) $user->token_version) {
                    return response()->json(['message' => 'Token has been revoked'], 401);
                }
            }

            // 1 round-trip Redis: lấy cả roles + permissions từ 1 key.
            $cached = app(PermissionCacheService::class)->get($user->id);

            $request->attributes->set('permissions', $cached['permissions']);
            $request->attributes->set('roles', $cached['roles']);
        } catch (TokenExpiredException $e) {
            return response()->json(['message' => 'Token has expired'], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['message' => 'Token is invalid'], 401);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token not provided'], 401);
        }

        return $next($request);
    }
}
