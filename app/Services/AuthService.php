<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Interface\AuthServiceInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Support\Facades\Auth;

class AuthService implements AuthServiceInterface
{
    protected $userRepository;


    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }
    public function register(array $data)
    {
        // 1. Tạo user thông qua repository
        $user = $this->userRepository->createUser($data);

        // 2. Gán vai trò mặc định
        $user->assignRole('student');

        return $user;
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

        if (!Auth::attempt($credentials)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ])->status(422);
        }

        RateLimiter::clear($key);

        $user = $this->userRepository->findByEmailUser($credentials['email']);

        if (!$user->hasAnyRole(['admin', 'teacher', 'student', 'parent', 'accountant', 'librarian', 'red_scarf', 'principal'])) {
            Auth::logout();
            throw new \Illuminate\Auth\Access\AuthorizationException('Insufficient permissions.');
        }

        $user->tokens()->delete();
        $token = $user->createToken('auth_token_for_' . $user->id)->plainTextToken;

        return [
            'user' => $user->load('profile', 'roles'),
            'token' => $token
        ];
    }
    public function logout($user)
    {
        return $user->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'User logged out sucessfully',

        ]);
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
