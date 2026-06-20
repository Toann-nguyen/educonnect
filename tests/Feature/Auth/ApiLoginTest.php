<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearTestRedis();
        $this->seedTestData();
    }

    protected function tearDown(): void
    {
        $this->clearTestRedis();
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    protected function clearTestRedis(): void
    {
        Redis::flushdb();
    }

    protected function seedTestData(): void
    {
        Role::firstOrCreate(['name' => 'student']);
        Role::firstOrCreate(['name' => 'admin']);

        User::factory()->create([
            'email'    => 'test@example.com',
            'password' => Hash::make('correct_password'),
            'is_active' => true,
        ])->assignRole('student');
    }

    // =========================================================================
    // TEST 1a: IP limit — Redis ZADD prepopulation (replaces 30-HTTP loop)
    // =========================================================================

    public function test_ip_limit_blocked_when_zset_has_30_entries()
    {
        $ip = '127.0.0.1';
        $ipKey = 'educonnect:auth:rate:ip:' . $ip;
        $now = microtime(true);

        for ($i = 0; $i < 30; $i++) {
            Redis::zadd($ipKey, $now - 30 + $i, (string) Str::uuid());
        }
        Redis::expire($ipKey, 60);

        $this->assertEquals(30, Redis::zcard($ipKey));

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(429);
        $response->assertJson(['blocked_by' => 'ip']);
        $this->assertArrayHasKey('retry_after', $response->json());

        $this->assertEquals(30, Redis::zcard($ipKey),
            'Lua script must not add entries beyond the IP limit');
    }

    // =========================================================================
    // TEST 1b: Pair limit — Redis ZADD prepopulation (replaces 10-HTTP loop)
    // =========================================================================

    public function test_pair_limit_blocked_when_zset_has_10_entries()
    {
        $ip = '127.0.0.1';
        $email = 'pair@example.com';
        $pairKey = 'educonnect:auth:rate:login:' . $ip . ':' . $email;
        $now = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            Redis::zadd($pairKey, $now - 30 + $i, (string) Str::uuid());
        }
        Redis::expire($pairKey, 60);

        $this->assertEquals(10, Redis::zcard($pairKey));

        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'wrong_password',
            'captcha_token' => 'valid_captcha_token',
        ]);

        $response->assertStatus(429);
        $response->assertJson(['blocked_by' => 'pair']);
        $this->assertArrayHasKey('retry_after', $response->json());

        $this->assertEquals(10, Redis::zcard($pairKey),
            'Lua script must not add entries beyond the pair limit');
    }

    // =========================================================================
    // TEST 2: NAT users — 20 requests same IP, different emails, ZCARD=20
    // =========================================================================

    public function test_nat_users_twenty_requests_allowed()
    {
        for ($i = 1; $i <= 20; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => "nat_user{$i}@example.com",
                'password' => 'wrong_password',
            ]);
            $this->assertEquals(401, $response->status(), "NAT user {$i} should return 401");
        }

        $ipKey = 'educonnect:auth:rate:ip:127.0.0.1';
        $this->assertEquals(20, Redis::zcard($ipKey),
            'IP ZSET should contain exactly 20 entries after 20 requests');
    }

    // =========================================================================
    // TEST 3: Account lock after 5 failed attempts (via API endpoint)
    // =========================================================================

    public function test_account_lock_after_five_failed_attempts()
    {
        $email = 'lock_test@example.com';

        $user = User::factory()->create([
            'email'    => $email,
            'password' => Hash::make('correct_password'),
            'is_active' => true,
        ]);
        $user->assignRole('student');

        for ($i = 1; $i <= 4; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => $email,
                'password' => 'wrong_password',
                'captcha_token' => 'valid_captcha_token',
            ]);
            $this->assertEquals(401, $response->status(), "Failed attempt {$i} should return 401");
            $this->assertArrayHasKey('attempts_left', $response->json());
        }

        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'wrong_password',
            'captcha_token' => 'valid_captcha_token',
        ]);
        $this->assertEquals(401, $response->status());
        $this->assertEquals(0, $response->json('attempts_left'));

        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'wrong_password',
            'captcha_token' => 'valid_captcha_token',
        ]);
        $response->assertStatus(423);
        $this->assertEquals('Account locked. Please try again later.', $response->json('message'));

        $lockKey = 'educonnect:auth:lock:127.0.0.1:' . $email;
        $this->assertNotNull(Redis::get($lockKey), 'Lock key should exist');
        $ttl = Redis::ttl($lockKey);
        $this->assertGreaterThan(800, $ttl, "Lock TTL should be ~900s, got {$ttl}");
    }

    // =========================================================================
    // TEST 4: Lock TTL — Carbon time travel + Redis cleanup → 200
    // =========================================================================

    public function test_account_lock_ttl_expires_after_15_minutes()
    {
        $email = 'lock_ttl_test@example.com';

        $user = User::factory()->create([
            'email'    => $email,
            'password' => Hash::make('correct_password'),
            'is_active' => true,
        ]);
        $user->assignRole('student');

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => $email,
                'password' => 'wrong_password',
                'captcha_token' => 'valid_captcha_token',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'correct_password',
            'captcha_token' => 'valid_captcha_token',
        ]);
        $response->assertStatus(423);

        Carbon::setTestNow(Carbon::now()->addMinutes(15));

        $lockKey = 'educonnect:auth:lock:127.0.0.1:' . $email;
        $attemptsKey = 'educonnect:auth:attempts:127.0.0.1:' . $email;
        Redis::del($lockKey, $attemptsKey);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'correct_password',
        ]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token']);
    }

    // =========================================================================
    // TEST 5: Captcha required after 3 failed attempts
    // =========================================================================

    public function test_captcha_required_after_three_failed_attempts()
    {
        $email = 'captcha_test@example.com';

        $user = User::factory()->create([
            'email'    => $email,
            'password' => Hash::make('correct_password'),
            'is_active' => true,
        ]);
        $user->assignRole('student');

        for ($i = 1; $i <= 2; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => $email,
                'password' => 'wrong_password',
            ]);
            $this->assertEquals(401, $response->status());
            $this->assertFalse($response->json('requires_captcha'),
                "Request {$i} should not require captcha");
            $this->assertEquals(5 - $i, $response->json('attempts_left'));
        }

        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'wrong_password',
        ]);
        $this->assertEquals(401, $response->status());
        $this->assertTrue($response->json('requires_captcha'),
            'Request 3 should flag captcha required');
        $this->assertEquals(2, $response->json('attempts_left'));

        $response = $this->postJson('/api/auth/login', [
            'email'    => $email,
            'password' => 'wrong_password',
        ]);
        $this->assertEquals(403, $response->status());
        $this->assertTrue($response->json('requires_captcha'));
        $this->assertEquals('Captcha required', $response->json('message'));
    }

    // =========================================================================
    // TEST 6: Captcha invalid token rejected
    // =========================================================================

    public function test_captcha_invalid_token_rejected()
    {
        $email = 'captcha_invalid_test@example.com';

        $user = User::factory()->create([
            'email'    => $email,
            'password' => Hash::make('correct_password'),
            'is_active' => true,
        ]);
        $user->assignRole('student');

        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => $email,
                'password' => 'wrong_password',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email'         => $email,
            'password'      => 'correct_password',
            'captcha_token' => 'invalid_token',
        ]);
        $this->assertEquals(403, $response->status());
        $this->assertTrue($response->json('requires_captcha'));
        $this->assertEquals('Captcha required', $response->json('message'));
    }

    // =========================================================================
    // TEST 7: CF-Connecting-IP used for rate-limiting Redis key
    // =========================================================================

    public function test_cf_connecting_ip_used_for_rate_limiting_key()
    {
        $response = $this->withHeaders([
            'CF-Connecting-IP' => '1.2.3.4',
            'X-Forwarded-For'  => '5.6.7.8',
        ])->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);

        $response->assertStatus(200);

        $ipKey = 'educonnect:auth:rate:ip:1.2.3.4';
        $this->assertSame(1, Redis::exists($ipKey),
            'Rate-limit key should be created with CF-Connecting-IP');

        $xffKey = 'educonnect:auth:rate:ip:5.6.7.8';
        $this->assertSame(0, Redis::exists($xffKey),
            'Rate-limit key should NOT use X-Forwarded-For when CF-IP is present');
    }

    // =========================================================================
    // TEST 8: Refresh token rate limit — 10 calls, 11th is 429
    // =========================================================================

    public function test_refresh_token_rate_limit_tenth_call_is_blocked()
    {
        $user = User::factory()->create([
            'email'    => 'refresh_test@example.com',
            'password' => Hash::make('correct_password'),
            'is_active'=> true,
        ]);
        $user->assignRole('student');

        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => 'refresh_test@example.com',
            'password' => 'correct_password',
        ]);
        $this->assertEquals(200, $loginResponse->status());

        $cookies = $loginResponse->headers->getCookies();
        $refreshToken = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'refresh_token') {
                $refreshToken = $cookie->getValue();
                break;
            }
        }
        $this->assertNotEmpty($refreshToken, 'Should have refresh token cookie');

        $this->withCredentials();

        for ($i = 1; $i <= 10; $i++) {
            $this->withUnencryptedCookie('refresh_token', $refreshToken);
            $response = $this->postJson('/api/auth/refresh');

            $this->assertEquals(200, $response->status(),
                "Refresh request {$i} should succeed");
            $this->assertArrayHasKey('access_token', $response->json());

            $cookies = $response->headers->getCookies();
            foreach ($cookies as $cookie) {
                if ($cookie->getName() === 'refresh_token') {
                    $refreshToken = $cookie->getValue();
                    break;
                }
            }
        }

        $this->withUnencryptedCookie('refresh_token', $refreshToken);
        $response = $this->postJson('/api/auth/refresh');
        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $this->assertArrayHasKey('retry_after', $response->json());
    }

    // =========================================================================
    // TEST 9: Basic login success
    // =========================================================================

    public function test_basic_login_success()
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'correct_password',
        ]);
        $this->assertEquals(200, $response->status());
        $this->assertArrayHasKey('access_token', $response->json());
    }
}
