# Login Endpoint — Test Plan & Implementation

> File: `tests/Feature/Auth/ApiLoginTest.php`

## Overview

The `POST /api/auth/login` endpoint is the most security‑critical route in the system. It enforces a multi‑layer defence:

| Layer | Mechanism | Scope | Code |
|-------|-----------|-------|------|
| **1. Dual sliding window** | Redis ZSET + Lua (IP‑wide 30r/m, IP+Email pair 10r/m) | Brute‑force / DDoS | `AuthService::checkRateLimit()` |
| **2. Account lock** | Redis key (auto‑lock after 5 failures, 15‑min TTL) | Credential stuffing | `AuthService::recordFailedAttempt()` |
| **3. Captcha gate** | Attempt counter ≥ 3 → require `captcha_token` | Slow attacks | `AuthService::requiresCaptcha()` |
| **4. Laravel middleware** | `rate.limit:refresh` (10r/m) for `/refresh` | Token abuse | `RateLimitMiddleware` |
| **5. Real‑IP resolution** | CF‑Connecting‑IP → X‑Forwarded‑For → `$remote_addr` | Proxy transparency | `RequestIp::resolve()` |

---

## Test Inventory

| ID | Test Method | What It Validates | Key Assertions |
|----|-------------|-------------------|----------------|
| 1a | `test_ip_limit_blocked_when_zset_has_30_entries` | Lua script rejects 31st IP entry | `429`, `blocked_by: 'ip'`, ZCARD=30 |
| 1b | `test_pair_limit_blocked_when_zset_has_10_entries` | Lua script rejects 11th pair entry | `429`, `blocked_by: 'pair'`, ZCARD=10 |
| 2 | `test_nat_users_twenty_requests_allowed` | 20 different emails from same IP | all `401`, IP ZCARD=20 |
| 3 | `test_account_lock_after_five_failed_attempts` | 5 wrong → lock, 6th → `423` | `attempts_left=0`, lock key TTL≈900 |
| 4 | `test_account_lock_ttl_expires_after_15_minutes` | Lock clears after 15 min | `423` → time travel → `200` |
| 5 | `test_captcha_required_after_three_failed_attempts` | Attempts ≥ 3 → captcha gate | 4th call without token → `403` |
| 6 | `test_captcha_invalid_token_rejected` | Invalid token → rejected | `403`, `requires_captcha: true` |
| 7 | `test_cf_connecting_ip_used_for_rate_limiting_key` | Real‑IP extraction uses CF‑IP | Redis key contains `1.2.3.4`, not `5.6.7.8` |
| 8 | `test_refresh_token_rate_limit_tenth_call_is_blocked` | 10 refresh calls → 11th `429` | `429`, `retry_after` present |
| 9 | `test_basic_login_success` | Happy path | `200`, `access_token` present |

---

## Test Details

### 1a — IP Limit (ZADD Prepopulation)

**Rationale:** Instead of making 30 HTTP requests (slow, side‑effects), we pre‑fill the ZSET with 30 scores via `Redis::zadd()`, then make **one** API call. This directly validates the Lua script’s threshold logic.

```
Given:  educonnect:auth:rate:ip:127.0.0.1  has 30 entries (within 60 s window)
When:   POST /api/auth/login  (different random email)
Then:   429, blocked_by='ip', ZCARD stays 30
```

**Key code:**
```php
Redis::zadd($ipKey, $timestamp, (string) Str::uuid());
// … 30 entries …
$this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
     ->assertStatus(429);
$this->assertEquals(30, Redis::zcard($ipKey));
```

### 1b — Pair Limit (ZADD Prepopulation)

Same technique for the IP+Email pair key (`educonnect:auth:rate:login:{ip}:{email}`).

```
Given:  educonnect:auth:rate:login:127.0.0.1:pair@example.com  has 10 entries
When:   POST /api/auth/login  (email = pair@example.com, valid captcha)
Then:   429, blocked_by='pair', ZCARD stays 10
```

### 2 — NAT Users

Simulates 20 users behind the same public IP. Each request uses a unique email and wrong password.

```
When:   20 × POST /api/auth/login  (email = nat_user{i}@example.com, wrong password)
Then:   All 401 (invalid credentials)
Then:   IP ZSET ZCARD = 20
```

### 3 — Account Lock After 5 Failures

Each request sends `captcha_token: valid_captcha_token` to bypass the captcha gate.

```
Steps:
  1-4.  wrong password → 401, attempts_left decrements (4→1)
  5.    wrong password → 401, attempts_left = 0 (lock key created)
  6.    wrong password → 423, "Account locked. Please try again later."
Verify: lock key TTL ≈ 900 s
```

### 4 — Lock TTL Expiry

Uses `Carbon::setTestNow()` to simulate 15 minutes passing, then cleans up Redis keys to mimic TTL expiry.

```
Steps:
  1-5.  wrong password → trigger lock
  6.    correct password → 423 (still locked)
  Time travel +15 min, Redis::del(lock & attempts keys)
  7.    correct password → 200, access_token present
```

### 5 — Captcha Required After 3 Fails

Steps 1-3 increment the attempt counter; step 4 is rejected because no captcha token is sent.

```
  1.   401, attempts_left=4, requires_captcha=false
  2.   401, attempts_left=3, requires_captcha=false
  3.   401, attempts_left=2, requires_captcha=true  (counter reached 3)
  4.   403, requires_captcha=true, "Captcha required"
```

### 6 — Captcha Invalid Token

After 3 failures, a correct password with `captcha_token: invalid_token` is rejected.

```
  3× wrong password → attempts ≥ 3
  correct password + invalid_token → 403
```

### 7 — CF‑Connecting‑IP Extraction

Sends both `CF-Connecting-IP` and `X-Forwarded-For` headers; asserts the Redis rate‑limit key uses the Cloudflare IP.

```
Headers: CF-Connecting-IP: 1.2.3.4, X-Forwarded-For: 5.6.7.8
POST /api/auth/login (correct credentials)
→ 200
→ educonnect:auth:rate:ip:1.2.3.4  EXISTS
→ educonnect:auth:rate:ip:5.6.7.8  DOES NOT EXIST
```

### 8 — Refresh Token Rate Limit

Logs in, extracts the `refresh_token` cookie, then calls `/api/auth/refresh` 11 times. The 11th call must return 429.

```
Login → 200, extract refresh_token from Set-Cookie
Loop 10×:
  POST /api/auth/refresh → 200, extract rotated refresh_token
11th:
  POST /api/auth/refresh → 429, retry_after present
```

**Important:** Each `/refresh` call rotates the token; the test tracks the new cookie value after each iteration.

### 9 — Basic Login Success

Simple happy‑path verification.

```
POST /api/auth/login (correct credentials)
→ 200
→ access_token present in JSON body
```

---

## Implementation Notes

- **Redis isolation:** `setUp()` calls `Redis::flushdb()` (test DB = 1 in `phpunit.xml`), guaranteeing each test starts with empty Redis.
- **Database isolation:** `RefreshDatabase` trait resets the DB between tests.
- **Real Redis for Lua:** Tests 1a/1b use real `Redis::zadd()` calls instead of mocking, validating both the Lua script and the PHP→Redis integration.
- **Time travel:** `Carbon::setTestNow()` is used in test 4 to simulate 15 min elapsed; PHP time‑aware logic (token expiry, attempt window) respects the shifted clock.
- **Cookie handling:** `withUnencryptedCookie('refresh_token', $value)` bypasses Laravel’s cookie encryption for test requests; `$response->headers->getCookies()` extracts rotated tokens.

---

## Running the Tests

```bash
# Single test class
php artisan test --filter=ApiLoginTest

# One specific test
php artisan test --filter=test_ip_limit_blocked_when_zset_has_30_entries

# Full feature suite
php artisan test --testsuite=Feature
```

Expected outcome: **9 tests, 9 passes**.
