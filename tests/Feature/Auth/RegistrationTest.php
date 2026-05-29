<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;
use App\Jobs\SendVerificationEmail;
use App\Events\UserRegistered;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Cần tạo role student vì AuthService/RegisterService mặc định gán role này
        Role::create(['name' => 'student', 'guard_name' => 'api']);
        
        // Dọn dẹp redis trước khi test
        $this->clearRedisIdempotencyKeys();
    }

    protected function tearDown(): void
    {
        $this->clearRedisIdempotencyKeys();
        parent::tearDown();
    }

    private function clearRedisIdempotencyKeys(): void
    {
        try {
            $keys = Redis::keys('*idempotency:*');
            foreach ($keys as $key) {
                // Xóa prefix tự động của Laravel nếu có
                $cleanKey = preg_replace('/^' . config('database.redis.options.prefix', '') . '/', '', $key);
                Redis::del($cleanKey);
            }
        } catch (\Exception $e) {
            // Bỏ qua lỗi nếu Redis chưa bật/chưa config
        }
    }

    public function test_user_can_register()
    {
        Queue::fake();
        Event::fake();

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            // Sử dụng mật khẩu cực kỳ phức tạp để tránh validation uncompromised() fail
            'password' => 'EduConnect2026!@#SecurePassword',
            'password_confirmation' => 'EduConnect2026!@#SecurePassword',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Registration successful. Please check your email for verification.',
                'data' => [
                    'email' => 'test@example.com',
                    'status' => 'UNVERIFIED',
                ]
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id', 'email', 'status'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        
        // Kiểm tra mật khẩu
        $this->assertTrue(Hash::check('EduConnect2026!@#SecurePassword', $user->password));
        $this->assertTrue(Hash::check('EduConnect2026!@#SecurePassword', $user->password_hash));
        
        // Kiểm tra role mặc định
        $this->assertTrue($user->hasRole('student'));

        // Kiểm tra Job gửi email được dispatch
        Queue::assertPushed(SendVerificationEmail::class, function ($job) use ($user) {
            return $job->user->id === $user->id;
        });
        
        // Kiểm tra bản ghi verification được tạo
        $this->assertDatabaseHas('email_verifications', [
            'user_id' => $user->id,
        ]);

        // Kiểm tra Event UserRegistered được dispatch
        Event::assertDispatched(UserRegistered::class, function ($event) use ($user) {
            return $event->user->id === $user->id && $event->user->email === $user->email;
        });
    }

    public function test_registration_validation_errors()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_failed_email_already_exists_returns_generic_error()
    {
        // Tạo trước 1 user
        User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $userData = [
            'name' => 'Test User',
            'email' => 'existing@example.com',
            'password' => 'EduConnect2026!@#SecurePassword',
            'password_confirmation' => 'EduConnect2026!@#SecurePassword',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        // Đảm bảo không tiết lộ email đã tồn tại (Email Enumeration Attack)
        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Registration failed. Please try again.'
            ]);
    }

    public function test_rate_limiting_on_register()
    {
        // Giả sử gọi liên tục 5 lần thành công/validation error, lần thứ 6 phải bị rate limit
        // Ta sử dụng email rỗng để đi qua validation nhanh nhưng vẫn kích hoạt Rate Limiter trước validation
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', []);
        }

        $response = $this->postJson('/api/auth/register', []);
        $response->assertStatus(429);
    }

    public function test_idempotency_on_register()
    {
        Queue::fake();

        $idempotencyKey = (string) Str::uuid();
        $userData = [
            'name' => 'Idempotent User',
            'email' => 'idempotent@example.com',
            'password' => 'EduConnect2026!@#SecurePassword',
            'password_confirmation' => 'EduConnect2026!@#SecurePassword',
        ];

        // Gửi Request 1: Tạo tài khoản thành công, trả về HTTP 201
        $response1 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/auth/register', $userData);

        $response1->assertStatus(201);
        $this->assertDatabaseCount('users', 1);

        // Gửi Request 2: Trả về HTTP 201 ngay lập tức từ cache, kiểm tra DB chỉ có đúng 1 bản ghi user được tạo
        $response2 = $this->withHeaders(['Idempotency-Key' => $idempotencyKey])
            ->postJson('/api/auth/register', $userData);

        $response2->assertStatus(201);
        
        // Kiểm tra xem database chỉ chứa đúng 1 user (không bị trùng lặp)
        $this->assertDatabaseCount('users', 1);
        
        // Nội dung response phải giống hệt nhau
        $this->assertEquals($response1->getContent(), $response2->getContent());
    }
}
