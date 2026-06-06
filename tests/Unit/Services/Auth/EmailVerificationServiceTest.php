<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\AuthService;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class EmailVerificationServiceTest extends TestCase
{
    private $userRepository;
    private $authRepository;
    private $emailVerificationRepository;
    private $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->authRepository = Mockery::mock(AuthRepositoryInterface::class);
        $this->emailVerificationRepository = Mockery::mock(EmailVerificationRepositoryInterface::class);

        $this->authService = new AuthService(
            $this->userRepository,
            $this->authRepository,
            $this->emailVerificationRepository
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_verify_email_successfully()
    {
        $rawToken = 'valid-token';
        $tokenHash = hash('sha256', $rawToken);
        
        $verification = (object)[
            'id' => 1,
            'user_id' => 10,
            'expires_at' => now()->addHour()
        ];

        // 1. Tìm token hash
        $this->emailVerificationRepository->shouldReceive('findByTokenHash')
            ->with($tokenHash)
            ->once()
            ->andReturn($verification);

        // 2. Cập nhật trạng thái user
        $this->authRepository->shouldReceive('update')
            ->with(10, Mockery::on(function ($data) {
                return $data['is_email_verified'] === true && isset($data['email_verified_at']);
            }))
            ->once()
            ->andReturn(true);

        // 3. Đánh dấu token đã verify
        $this->emailVerificationRepository->shouldReceive('markAsVerified')
            ->with(1)
            ->once();

        $result = $this->authService->verifyEmail($rawToken);

        $this->assertTrue($result);
    }

    public function test_verify_email_fails_with_invalid_token()
    {
        $rawToken = 'invalid-token';
        $tokenHash = hash('sha256', $rawToken);

        $this->emailVerificationRepository->shouldReceive('findByTokenHash')
            ->with($tokenHash)
            ->once()
            ->andReturn(null);

        $this->expectException(ValidationException::class);
        
        $this->authService->verifyEmail($rawToken);
    }

    public function test_verify_email_fails_with_expired_token()
    {
        $rawToken = 'expired-token';
        $tokenHash = hash('sha256', $rawToken);
        
        $verification = (object)[
            'id' => 1,
            'user_id' => 10,
            'expires_at' => now()->subHour() // Đã hết hạn
        ];

        $this->emailVerificationRepository->shouldReceive('findByTokenHash')
            ->with($tokenHash)
            ->once()
            ->andReturn($verification);

        $this->expectException(ValidationException::class);
        
        $this->authService->verifyEmail($rawToken);
    }
}
