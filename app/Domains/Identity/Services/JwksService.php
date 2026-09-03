<?php

namespace App\Domains\Identity\Services;

use Illuminate\Support\Facades\Cache;

/**
 * JWKS Service — publishes RS256 (and optional EdDSA) public keys as JWK Set.
 * Private key stays server-side; only public JWK is exposed.
 */
class JwksService
{
    public function getJwks(): array
    {
        try {
            return Cache::remember('jwks', 3600, fn () => $this->buildJwks());
        } catch (\Throwable $e) {
            return $this->buildJwks();
        }
    }

    private function buildJwks(): array
    {
        $keys = [];

        $rs256 = $this->buildRsaJwk(
            publicKeyPath: $this->resolvePublicKeyPath(),
            kid: config('jwt.kid', 'educonnect-rs256-1'),
            alg: config('jwt.algo', 'RS256')
        );
        if ($rs256) {
            $keys[] = $rs256;
        }

        $eddsaPublic = storage_path('certs/jwt-eddsa-public.pem');
        if (file_exists($eddsaPublic)) {
            $eddsa = $this->buildOctetJwk(
                publicKeyPath: $eddsaPublic,
                kid: env('JWT_EDDSA_KID', 'educonnect-eddsa-1'),
                alg: 'EdDSA'
            );
            if ($eddsa) {
                $keys[] = $eddsa;
            }
        }

        return ['keys' => $keys];
    }

    public function getPublicPem(): ?string
    {
        $path = $this->resolvePublicKeyPath();
        if (! file_exists($path)) {
            return null;
        }
        return file_get_contents($path) ?: null;
    }

    public function clearCache(): void
    {
        try {
            Cache::forget('jwks');
        } catch (\Throwable $e) {
        }
    }

    private function resolvePublicKeyPath(): string
    {
        $configured = config('jwt.keys.public');
        if ($configured) {
            $path = str_starts_with($configured, 'file://') ? substr($configured, 7) : $configured;
            if (! str_starts_with($path, '/')) {
                $path = storage_path(ltrim($path, '/'));
            }
            if (file_exists($path)) {
                return $path;
            }
        }
        foreach ([
            storage_path('certs/jwt-public.pem'),
            storage_path('certs/jwt-rsa-4096-public.pem'),
            base_path('storage/certs/jwt-public.pem'),
        ] as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
        return storage_path('certs/jwt-public.pem');
    }

    private function buildRsaJwk(string $publicKeyPath, string $kid, string $alg): ?array
    {
        if (! file_exists($publicKeyPath)) {
            return null;
        }
        $pem = file_get_contents($publicKeyPath);
        $key = openssl_pkey_get_public($pem);
        if (! $key) {
            return null;
        }
        $details = openssl_pkey_get_details($key);
        if (! isset($details['rsa']['n'], $details['rsa']['e'])) {
            return null;
        }
        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => $kid,
            'alg' => $alg,
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    private function buildOctetJwk(string $publicKeyPath, string $kid, string $alg): ?array
    {
        if (! file_exists($publicKeyPath)) {
            return null;
        }
        $pem = file_get_contents($publicKeyPath);
        $key = openssl_pkey_get_public($pem);
        if (! $key) {
            return null;
        }
        $b64 = preg_replace('/-----(BEGIN|END) PUBLIC KEY-----/', '', $pem);
        $b64 = str_replace(["\r", "\n", ' '], '', $b64);
        $der = base64_decode($b64, true);
        if (! $der || strlen($der) < 32) {
            return null;
        }
        $raw = substr($der, -32);
        return [
            'kty' => 'OKP',
            'use' => 'sig',
            'kid' => $kid,
            'alg' => $alg,
            'crv' => 'Ed25519',
            'x' => $this->base64UrlEncode($raw),
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
