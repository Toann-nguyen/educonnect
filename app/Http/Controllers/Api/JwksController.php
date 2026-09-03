<?php

namespace App\Http\Controllers\Api;

use App\Domains\Identity\Services\JwksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JwksController
{
    public function __construct(private readonly JwksService $jwks) {}

    /**
     * GET /.well-known/jwks.json  and  GET /api/jwks  and  GET /api/auth/jwks
     */
    public function index(): JsonResponse
    {
        return response()->json($this->jwks->getJwks(), 200, [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * GET /.well-known/openid-configuration
     */
    public function openIdConfiguration(Request $request): JsonResponse
    {
        $base = rtrim(config('app.url', $request->getSchemeAndHttpHost()), '/');
        // Prefer JWT_ISS as issuer if configured
        $issuer = config('jwt.iss', $base);

        return response()->json([
            'issuer' => $issuer,
            'jwks_uri' => $base . '/.well-known/jwks.json',
            'id_token_signing_alg_values_supported' => [config('jwt.algo', 'RS256'), 'EdDSA'],
            'claims_supported' => ['sub', 'iss', 'aud', 'iat', 'exp', 'jti', 'ver', 'type'],
            'subject_types_supported' => ['public'],
        ], 200, [
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * GET /api/auth/public-key  — raw PEM for debugging (optional, not JWKS)
     */
    public function publicKey(): \Illuminate\Http\Response
    {
        $pem = $this->jwks->getPublicPem();
        if (! $pem) {
            return response('Public key not configured', 404);
        }
        return response($pem, 200, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
