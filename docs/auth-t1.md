# Auth T1 — As-built (T1.1 Ký số/JWKS + T1.5 Rate limit/Audit)

> Phạm vi: tổng hợp T1.1 + T1.5 từ code thật trên nhánh `feature/auth`.
> Nguồn đọc trực tiếp: `composer.json`, `composer.lock`, `config/jwt.php`,
> `app/Domains/Identity/Services/JwksService.php`,
> `app/Http/Controllers/Api/JwksController.php`,
> `app/Domains/Identity/Middleware/JwtMiddleware.php`,
> `app/Http/Middleware/CheckRoleAndPermission.php`,
> `app/Http/Middleware/JsonRoleMiddleware.php`,
> `app/Http/Kernel.php`, `app/Providers/RouteServiceProvider.php`,
> `routes/api.php`, `routes/web.php`, `routes/identity.php`,
> `app/Domains/Identity/Services/AuthService.php` (mint + dispatchAudit),
> `app/Domains/Identity/Models/AuditLog.php`, `.env.example`, `.gitignore`,
> `storage/certs/` (stat + openssl). Không suy diễn ngoài những gì đọc được.

## 1. Quyết định version (T1.1)

| Thành phần | Version đọc được | Ghi chú |
|---|---|---|
| `php-open-source-saver/jwt-auth` | `composer.json: ^2.8.3`, `composer.lock: v2.8.3` | Giữ 2.8.3 — 2.9.x đòi Laravel 12 / PHP 8.3 trong khi app là Laravel 10.50 / PHP `^8.1` (`composer.json`, lock `laravel/framework v10.50.3`) |
| `lcobucci/jwt` (provider ký số) | `composer.lock: 5.6.0` | Provider trong `config/jwt.php`: `PHPOpenSourceSaver\JWTAuth\Providers\JWT\Lcobucci::class` |
| Thuật toán ký | `config/jwt.php: 'algo' => env('JWT_ALGO','RS256')`, `.env.example: JWT_ALGO=RS256` | Access token RS256; JWKS kèm thêm EdDSA nếu có file public |

Các default liên quan trong `config/jwt.php` (đọc được):
`ttl = env(JWT_TTL, 10)` phút, `refresh_ttl = env(JWT_REFRESH_TTL, 10080)` phút,
`jwks_ttl = env(JWT_JWKS_TTL, 3600)` giây, `refresh_grace = env(JWT_REFRESH_GRACE, 30)` giây,
`leeway = 0`, `blacklist_enabled = true`, `blacklist_grace_period = 30`,
`lock_subject = false`, `decrypt_cookies = false`.

## 2. Keypair (T1.1)

- `storage/certs/` thực tế (stat ngày viết doc): `jwt-private.pem` **600**,
  `jwt-public.pem` 644, `jwt-eddsa-private.pem` **600**, `jwt-eddsa-public.pem` 644,
  kèm cặp legacy `jwt-rsa-4096-private/public.pem` (644, từ Jun 16).
- `openssl` verify: `jwt-private.pem` = **RSA 2048 bit** (`Private-Key: (2048 bit, 2 primes)`),
  `jwt-eddsa-private.pem` = **ED25519** (`ED25519 Private-Key`).
- `.gitignore` (dòng 65–68): private **gitignored** (`storage/certs/jwt-private.pem`,
  `storage/certs/jwt-eddsa-private.pem`, `storage/certs/*.key`) — public được publish qua JWKS.
- Cấu hình: `.env.example` `JWT_PRIVATE_KEY=file://storage/certs/jwt-private.pem`,
  `JWT_PUBLIC_KEY=file://storage/certs/jwt-public.pem`, `JWT_PASSPHRASE=` (trống).
- `JwksService::resolvePublicKeyPath()`: ưu tiên `config('jwt.keys.public')`
  (bóc prefix `file://`, path tương đối → `storage_path()`), fallback
  `storage/certs/jwt-public.pem` → `storage/certs/jwt-rsa-4096-public.pem`.
  EdDSA chỉ publish khi tồn tại `storage/certs/jwt-eddsa-public.pem`.

## 3. Claims (T1.1)

- `config/jwt.php`: `iss = env(JWT_ISS, env(APP_URL,…))`, `aud = env(JWT_AUD,'educonnect-api')`,
  `kid = env(JWT_KID,'educonnect-rs256-1')`; `.env.example`: `JWT_ISS=${APP_URL}`,
  `JWT_AUD=educonnect-api`, `JWT_KID=educonnect-rs256-1`, `JWT_EDDSA_KID=educonnect-eddsa-1`.
- `required_claims = [sub, jti, iat, exp]`.
- `AuthService::mintAccessToken()` (`app/Domains/Identity/Services/AuthService.php:484`)
  gắn thêm claims ngoài chuẩn tymon: `jti` (uuid), `sid` (session id hoặc user id),
  `tv` + `ver` (= `token_version` của user, dùng cho thu hồi stateless), `roles` (mảng tên role).
- `JwksController::openIdConfiguration()` công bố
  `claims_supported = [sub, iss, aud, iat, exp, jti, sid, tv, ver, roles, type]`,
  `id_token_signing_alg_values_supported = [config('jwt.algo'), 'EdDSA']`,
  `subject_types_supported = [public]`.

## 4. Endpoints JWKS (T1.1 — public, không auth)

`routes/api.php` (prefix `api` từ `RouteServiceProvider`):

- `GET /api/jwks` → `JwksController@index`
- `GET /api/auth/jwks` → `JwksController@index`
- `GET /api/auth/public-key` → `JwksController@publicKey` (PEM thô, `text/plain`; 404 nếu chưa cấu hình)

`routes/web.php`:

- `GET /.well-known/jwks.json` → `JwksController@index` (RFC 7517)
- `GET /.well-known/openid-configuration` → `JwksController@openIdConfiguration`
  (`issuer` = `config('jwt.iss')` fallback `app.url`, `jwks_uri = {app.url}/.well-known/jwks.json`)

Hành vi đọc được từ `JwksController` + `JwksService`:

- `index()` trả JSON `['keys' => […]]`, header `Cache-Control: public, max-age={jwt.jwks_ttl}`
  (mặc định 3600) — khớp mô tả public cache 3600s.
- Key RSA: `{kty:RSA, use:sig, kid, alg, n, e}` (n/e base64url từ `openssl_pkey_get_details`).
  Key EdDSA (nếu có file): `{kty:OKP, use:sig, kid, alg:EdDSA, crv:Ed25519, x}` (32 byte cuối của DER public key).
- Cache: `Cache::remember('jwks', ttl, buildJwks)`; `try/catch` — hỏng cache thì build trực tiếp;
  `clearCache()` quên key `jwks`. `getPublicPem()` đọc file public key, null nếu thiếu.

## 5. RBAC / middleware hiện có (liên quan T1.1)

`app/Http/Kernel.php` `$middlewareAliases` (đọc được):

- `auth.jwt` → `App\Domains\Identity\Middleware\JwtMiddleware`
- `role` → `App\Http\Middleware\JsonRoleMiddleware` (extends Spatie `RoleMiddleware`, bắt
  `UnauthorizedException` → JSON 403 + `required_roles`)
- `permission` → Spatie `PermissionMiddleware`, `role_or_permission` → Spatie `RoleOrPermissionMiddleware`
- `rbac` / `check.role.permission` / `check.role` → `App\Http\Middleware\CheckRoleAndPermission`
  (comment trong Kernel: `T1.4 RBAC — CheckRoleAndPermission + token version check`)
- Còn có: `rate.register`, `rate.limit`, `role.redirect`, `validate.api`, `idempotence`.

`JwtMiddleware::handle()` (đọc được):

1. `JWTAuth::parseToken()->authenticate()` — 404 nếu user không tồn tại.
2. Chặn **pre-auth 2FA**: claim `pre_auth` → 401 `2FA verification required`.
3. Check **token version**: claim `tv` (fallback `ver`) so với `$user->token_version` — lệch → 401 `Token has been revoked`.
4. **Permission cache Redis**: `PermissionCacheService::get($user->id)` — 1 round-trip
   (`Redis::get user:{id}:permissions`, xem `PermissionCacheService`: `setex` TTL + `del` khi đổi quyền),
   nạp `permissions` + `roles` vào `$request->attributes`.

`CheckRoleAndPermission::handle($request, $next, ...$rolesOrPermissions)`:

1. Đòi `$request->user()` (không có → `AuthenticationException`).
2. Re-check version `ver`/`tv` từ JWT (nếu có token) — mismatch → 401 revoked.
3. Không tham số → pass (chỉ auth + version). Có tham số: `'|'` = OR
   (`hasAnyRole`/`hasAnyPermission` + fallback từng phần `hasRole`/`can`),
   `'&'` = AND (`hasAllRoles`/`hasAllPermissions` + fallback mixed); fail → `AuthorizationException` 403.

## 6. Rate limit + Audit (T1.5)

`app/Providers/RouteServiceProvider::boot()` (đọc được):

- `api`: 60/phút theo user id (fallback IP).
- `register`: 5/phút theo IP (lưu ý: `AppServiceProvider` cũng đăng ký limiter `register` 5/phút/IP — trùng tên, định nghĩa sau thắng).
- `login`: 5/phút/IP **+** 15 lần/15 phút/email (lowercased).
- `refresh`: 10/phút/IP.
- `password-reset`: `forgot-password` 3/giờ/email; còn lại 3/giờ/IP.

`routes/identity.php` gắn: `register`→`throttle:register`, `login`→`throttle:login`,
`forgot-password`/`reset-password`→`throttle:password-reset`,
`email/verify`→`throttle:6,1`, `refresh`→`throttle:refresh`;
`GET /auth/me`, `POST /auth/logout`, `/auth/logout/all` sau `auth.jwt`;
route 2FA/email/sessions chỉ đăng ký khi controller tồn tại; admin roles/permissions sau
`auth.jwt` + `role:admin` khi `RoleService` tồn tại.

Audit (đọc được từ `AuthService::dispatchAuth` + `AuditLog`):

- `AuthService::dispatchAudit()` → `WriteAuditLog::dispatch(userId, action, ip, userAgent, metadata)->onQueue('audit')`
  (queue `audit`, best-effort try/catch).
- Tác vụ thấy trong code: `TOKEN_REFRESH`, `TOKEN_REFRESH_REPLAY`, `REFRESH_TOKEN_REUSE_DETECTED`.
- Model `AuditLog` (`$connection = 'identity'`, `$timestamps = false`):
  fillable `user_id, action, ip_address, user_agent, metadata` (+ cast `metadata => array`).
