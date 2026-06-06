<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use App\Services\Interface\AuthServiceInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendVerificationEmail;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected AuthRepositoryInterface $authRepository,
        protected EmailVerificationRepositoryInterface $emailVerificationRepository
    ) {}

    public function register(array $data)
    {
        // 1. Tạo user thông qua auth repository
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user = $this->authRepository->create($data);

        // 2. Gán vai trò mặc định
        $user->assignRole('student');

        // 3. Tạo token xác minh email
        $rawToken = Str::random(60);
        $tokenHash = hash('sha256', $rawToken);

        $this->emailVerificationRepository->upsert(
            $user->id,
            $tokenHash,
            now()->addHours(24)
        );

        // 4. Gửi email xác minh qua Queue Job
        SendVerificationEmail::dispatch($user, $rawToken);

        // 5. Tự động tạo Access Token (Auto-login)
        $token = auth('api')->login($user);

        return [
            'user'  => $user->load('profile', 'roles'),
            'token' => $token
        ];
    }

    public function login(array $credentials)
    {
        $key = strtolower($credentials['email']) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->status(429);
        }

        // Sử dụng guard api (JWT)
        $token = auth('api')->attempt($credentials);

        if (!$token) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ])->status(422);
        }

        RateLimiter::clear($key);

        $user = auth('api')->user();

        if (!$user->hasAnyRole(['admin', 'teacher', 'student', 'parent', 'accountant', 'librarian', 'red_scarf', 'principal'])) {
            auth('api')->logout();
            throw new \Illuminate\Auth\Access\AuthorizationException('Insufficient permissions.');
        }

        return [
            'user' => $user->load('profile', 'roles'),
            'token' => $token
        ];
    }
    
    public function logout($user)
    {
        auth('api')->logout();

        return response()->json([
            'success' => true,
            'message' => 'User logged out successfully',
        ]);
    }

    public function verifyEmail(string $token)
    {
        $tokenHash = hash('sha256', $token);
        
        $verification = $this->emailVerificationRepository->findByTokenHash($tokenHash);
        
        if (!$verification || $verification->expires_at < now()) {
            throw ValidationException::withMessages([
                'token' => 'Token xác minh không hợp lệ hoặc đã hết hạn.'
            ]);
        }

        $this->authRepository->update($verification->user_id, [
            'is_email_verified' => true,
            'email_verified_at' => now(),
        ]);

        $this->emailVerificationRepository->markAsVerified($verification->id);

        return true;
    }

    public function forgotPassword(array $data)
    {
        return Password::sendResetLink($data);
    }

    public function resetPassword(array $data)
    {
        return Password::reset($data, function ($user, $password) {
            $this->userRepository->updateUser($user->id, ['password' => $password]);
        });
    }
}
