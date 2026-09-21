# T1.1 JWT RS256 / JWKS — EduConnect

## Quyết định
- **Chọn upgrade jwt-auth 2.8.3 RS256 + JWKS** (Passport không dùng). Lý do: monolith đang dùng `php-open-source-saver/jwt-auth`, giữ tương thích guard `api`/`auth.jwt` hiện tại, tránh thêm OAuth2 server nặng. Passport phù hợp khi cần OAuth2 Authorization Code + Client Credentials; hiện tại chỉ cần asymmetric signing cho microservice verification.
- `jwt-auth 2.9.2` yêu cầu `illuminate/auth ^12|^13` (Laravel 12) → không tương thích Laravel 10 trên nhánh này. Chọn `v2.8.3` là bản cao nhất hỗ trợ `^10|^11|^12` và `lcobucci/jwt ^5.4` (tương đương 2.9.2 về RS256/JWKS).
- `spatie/laravel-permission 8.3.0` cũng yêu cầu Laravel 12 → giữ `6.25.0` (hỗ trợ `^8.12|^9|^10|^11|^12|^13`, đã cover đầy đủ).

## Keypair
- RS256 2048-bit: `storage/certs/jwt-private.pem` (600, gitignored) + `jwt-public.pem` (644, committed)
- EdDSA Ed25519 secondary: `jwt-eddsa-private.pem` (gitignored) + `jwt-eddsa-public.pem` (committed)
- Private key **không** ra khỏi server; public key publish qua JWKS.
- Artisan: `php artisan jwt:generate-keys [--bits=2048] [--force]`

## Cấu hình
- `config/jwt.php` thêm `iss/aud/kid` đọc từ env `JWT_ISS/JWT_AUD/JWT_KID` (mặc định `APP_URL` / `educonnect-api` / `educonnect-rs256-1`)
- `.env` / `.env.example` thêm block `JWT_ALGO=RS256`, `JWT_PRIVATE_KEY=file://storage/certs/jwt-private.pem`, `JWT_PUBLIC_KEY=file://...`, `JWT_ISS`, `JWT_AUD`, `JWT_KID`, `JWT_EDDSA_KID`
- `composer.json` chốt `php-open-source-saver/jwt-auth ^2.8.3`, `audit.block-insecure=false` tạm để vượt security advisory Laravel 10 trong `composer update -W` (cần revert khi lên Laravel 11/12)
- `User::getJWTCustomClaims()` thêm `iss/aud` từ config

## Endpoints (public, không auth)
- `GET /.well-known/jwks.json` — JWKS RFC7517 (kty RSA + OKP Ed25519), Cache 3600s
- `GET /.well-known/openid-configuration` — OIDC Discovery (issuer, jwks_uri, algs)
- `GET /api/jwks`, `GET /api/auth/jwks`, `GET /api/auth/public-key` (PEM raw, debug)

## Kiểm thử
- `php artisan route:list --path=jwks` — 5 routes
- `CACHE_STORE=array php artisan tinker --execute="app(JwksService::class)->getJwks()"` — trả về 2 keys, `n/e` base64url, `x` cho Ed25519
- Token RS256 sign/verify bằng `lcobucci/jwt 5.6` với `kid` header, `iss/aud` claims

## Còn lại
- Khi gateway/identity tách thành service riêng (`educonnect-identity`), copy cặp `storage/certs/*.pem` hoặc mount secret; `educonnect` school worker verify bằng JWKS fetch.
- Rotation: tạo key mới với `kid` mới, append vào JWKS array, giữ key cũ cho grace period.
