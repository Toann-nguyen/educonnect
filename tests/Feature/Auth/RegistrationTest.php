<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SendVerificationEmail;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Cần tạo role student vì AuthService mặc định gán role này
        Role::create(['name' => 'student', 'guard_name' => 'api']);
    }

    public function test_user_can_register()
    {
        Queue::fake();

        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id', 'name', 'email', 'roles'
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        
        // Kiểm tra mật khẩu (getAuthPassword sẽ trả về password column nếu password_hash là null)
        $this->assertTrue(Hash::check('Password123', $user->getAuthPassword()));
        
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
}
