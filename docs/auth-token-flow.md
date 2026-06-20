# EduConnect — Auth Token Flow

## Token Architecture

| Token | TTL | Storage | Transport | Protection |
|-------|-----|---------|-----------|------------|
| Access Token (JWT) | 15 phút (`JWT_TTL=15`) | In-memory (React state/Zustand) | `Authorization: Bearer <token>` | XSS-resistant (không localStorage) |
| Refresh Token | 7 ngày | HttpOnly Cookie (`refresh_token`) | Cookie header tự động | XSS-proof (JS không đọc được), CSRF-safe (SameSite=Strict) |

---

## Cookie Configuration

```php
cookie(
    name: 'refresh_token',
    value: $rawRefreshToken,
    minutes: 60 * 24 * 7,          // 7 ngày
    path: '/api/auth/',             // trailing slash → match /api/auth/refresh, /logout, /logout/all
    domain: null,
    secure: app()->isProduction(),  // HTTPS-only trên production
    httpOnly: true,                 // JS không đọc được → chống XSS
    raw: false,
    sameSite: 'Strict'              // không gửi trong cross-site request → chống CSRF
);
```

**Tại sao `/api/auth/` (trailing slash)?**
- Browser matching: path `/api/auth/` match tất cả sub-path bắt đầu bằng `/api/auth/`
- Bao phủ: `/api/auth/refresh`, `/api/auth/logout`, `/api/auth/logout/all`
- Không dùng `/` để tránh gửi cookie cho toàn bộ API

---

## Full Flow Diagram

### Login Flow
```
Frontend                  Backend                    Redis/DB
   │                         │                          │
   │── POST /api/auth/login ─►│                          │
   │   {email, password}      │                          │
   │                         │── validate credentials ──►│
   │                         │── issue tokens ──────────►│
   │                         │   - access_token (JWT)    │
   │                         │   - refresh_token (hash)  │
   │◄── 200 OK ──────────────│                          │
   │   body: {access_token}  │                          │
   │   header: Set-Cookie:   │                          │
   │     refresh_token=xxx;  │                          │
   │     HttpOnly; Secure;   │                          │
   │     SameSite=Strict;    │                          │
   │     Path=/api/auth/     │                          │
   │                         │                          │
   │ [Frontend lưu           │                          │
   │  access_token vào RAM]  │                          │
```

### Request Flow (access token còn hạn)
```
Frontend                  Backend                    Redis
   │                         │                          │
   │── GET /api/... ─────────►│                          │
   │   Authorization:         │                          │
   │   Bearer <access_token>  │                          │
   │                         │── JwtMiddleware ─────────►│
   │                         │   1. verify JWT sig       │
   │                         │   2. check ver claim      │
   │                         │   3. load permissions ───►│
   │                         │      (user:{id}:perms)    │
   │◄── 200 OK ──────────────│                          │
```

### Reload Page / Access Token Expired Flow
```
Frontend                  Backend                    Redis/DB
   │                         │                          │
   │ [RAM cleared → access   │                          │
   │  token mất]             │                          │
   │                         │                          │
   │── POST /api/auth/refresh►│                          │
   │   [browser tự gửi       │                          │
   │    Cookie: refresh_      │                          │
   │    token=xxx]           │                          │
   │   [KHÔNG cần Bearer]    │                          │
   │                         │── hash token ────────────►│
   │                         │── find RefreshToken ─────►│
   │                         │── theft detection ────────│
   │                         │── rotate token ───────────│
   │                         │   (revoke old, issue new) │
   │◄── 200 OK ──────────────│                          │
   │   body: {access_token}  │                          │
   │   header: Set-Cookie:   │                          │
   │     refresh_token=NEW   │                          │
   │                         │                          │
   │ [Frontend lưu lại       │                          │
   │  access_token mới]      │                          │
   │ [User không biết gì]    │                          │
```

### Logout Flow
```
Frontend                  Backend
   │                         │
   │── POST /api/auth/logout ►│
   │   Authorization: Bearer  │ ← access token còn valid
   │   Cookie: refresh_token  │ ← browser tự gửi
   │                         │── auth('api')->logout()   ← blacklist access token
   │                         │── revoke refresh token    ← hash → DB lookup → mark revoked
   │                         │── Redis::del session key  ← clear session cache
   │◄── 200 OK ──────────────│
   │   Set-Cookie:           │
   │     refresh_token=;     │ ← cookie cleared (max-age=-1)
   │     expires=Thu, 01 ... │
```

---

## Theft Detection (Refresh Token Reuse)

```
Kẻ tấn công có stolen refresh token
         │
         ▼
POST /api/auth/refresh với token đã bị revoke
         │
         ▼
AuthService::refresh() → RefreshToken::where('token_hash', $hash)
         │
         ├─► record found + revoked_at != null
         │         │
         │         ▼
         │   THEFT DETECTED! → Revoke ALL sessions của user đó
         │   → Delete tất cả UserSession
         │   → Dispatch audit log: REFRESH_TOKEN_REUSE_DETECTED
         │   → Throw InvalidCredentialsException (401)
         │
         └─► User bị force logout toàn bộ thiết bị
```

---

## Backend Configuration Checklist

| Requirement | Status | Config |
|---|---|---|
| `/refresh` không cần Bearer | ✅ | Route nằm ngoài `auth.jwt` middleware |
| CORS `supports_credentials` | ✅ | `config/cors.php`: `'supports_credentials' => true` |
| Cookie HttpOnly | ✅ | `httpOnly: true` trong `makeRefreshCookie()` |
| Cookie SameSite=Strict | ✅ | `sameSite: 'Strict'` |
| Cookie Secure (HTTPS) | ✅ | `secure: app()->isProduction()` |
| Cookie Path | ✅ Fixed | `/api/auth/` (trailing slash) |
| `AddQueuedCookiesToResponse` | ✅ Fixed | Thêm vào `api` middleware group |
| Sanctum không conflict | ✅ Fixed | Đã remove khỏi global + api middleware |
| refresh_token exempt encrypt | ✅ | `EncryptCookies::$except = ['refresh_token']` |
| Token rotation | ✅ | `AuthService::refresh()` rotate token mỗi lần dùng |

---

## Frontend Requirements

```typescript
// Axios instance configuration
const api = axios.create({
  baseURL: '/api',
  withCredentials: true,  // BẮT BUỘC để browser gửi HttpOnly cookie
});

// In-memory token storage (KHÔNG dùng localStorage!)
let accessToken: string | null = null;

// Interceptor: attach Bearer token
api.interceptors.request.use((config) => {
  if (accessToken) {
    config.headers.Authorization = `Bearer ${accessToken}`;
  }
  return config;
});

// Interceptor: auto-refresh khi 401
api.interceptors.response.use(
  (res) => res,
  async (error) => {
    if (error.response?.status === 401 && !error.config._retry) {
      error.config._retry = true;
      try {
        const { data } = await axios.post('/api/auth/refresh', {}, {
          withCredentials: true,  // cookie tự gửi
        });
        accessToken = data.access_token;
        error.config.headers.Authorization = `Bearer ${accessToken}`;
        return api(error.config);
      } catch {
        accessToken = null;
        // redirect to login
      }
    }
    return Promise.reject(error);
  }
);
```

---

## Common Pitfalls

| Pitfall | Mô tả | Fix |
|---|---|---|
| `Path=/api/auth` (không trailing slash) | Browser chỉ match exact path trên một số phiên bản | Dùng `/api/auth/` |
| `withCredentials: false` ở frontend | Browser không gửi cookie → /refresh luôn 401 | Set `withCredentials: true` |
| `SameSite=None` không có `Secure` | Browser từ chối cookie | Cần HTTPS hoặc dùng `SameSite=Lax` trên dev |
| Double refresh race condition | 2 request cùng lúc → 401 → 2 lần gọi /refresh → rotation conflict | Implement refresh promise queue (singleton) |
| Sanctum + JWT conflict | `EnsureFrontendRequestsAreStateful` override cookie handling | Remove Sanctum middleware khỏi API group |
| `localStorage` cho access token | Vulnerable to XSS → attacker đọc được token | Dùng in-memory (RAM) |
