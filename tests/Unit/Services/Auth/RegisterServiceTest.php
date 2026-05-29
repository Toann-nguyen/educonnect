<?php

namespace Tests\Unit\Services\Auth;

use App\Events\UserRegistered;
use App\Jobs\SendVerificationEmail;
use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use App\Services\Auth\RegisterService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class RegisterServiceTest extends TestCase
{
    private $authRepository;
    private $emailVerificationRepository;
    private $registerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authRepository = Mockery::mock(AuthRepositoryInterface::class);
        $this->emailVerificationRepository = Mockery::mock(EmailVerificationRepositoryInterface::class);

        $this->registerService = new RegisterService(
            $this->authRepository,
            $this->emailVerificationRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_register_successfully()
    {
        Queue::fake();
        Event::fake();

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
        ];

        // 1. Giả lập email chưa tồn tại
        $this->authRepository->shouldReceive('findByEmail')
            ->with($data['email'])
            ->andReturn(null);

        // 2. Giả lập tạo user
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->email = $data['email'];
        $user->name = $data['name'];

        // Mock method assignRole và hasRole (của Spatie)
        $user->shouldReceive('assignRole')->with('student')->once();
        
        $this->authRepository->shouldReceive('create')
            ->once()
            ->andReturn($user);

        // 3. Giả lập upsert token
        $this->emailVerificationRepository->shouldReceive('upsert')
            ->once();

        // Chạy logic register
        // Lưu ý: DB::transaction sẽ thực thi closure trực tiếp trong test (mặc định)
        $result = $this->registerService->register($data);

        $this->assertEquals($user, $result);

        // Kiểm tra Event và Job
        Event::assertDispatched(UserRegistered::class);
        Queue::assertPushed(SendVerificationEmail::class);
    }

    public function test_register_fails_if_email_exists()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ];

        $this->authRepository->shouldReceive('findByEmail')
            ->with($data['email'])
            ->andReturn(new User());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Registration failed. Please try again.');
        $this->expectExceptionCode(422);

        $this->registerService->register($data);
    }
}
