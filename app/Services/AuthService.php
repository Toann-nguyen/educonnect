<?php

namespace App\Services;

use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Interface\AuthServiceInterface;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Exception;

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
        // Tạo một key duy nhất dựa trên email và địa chỉ IP
        // 1. Chuẩn bị và kiểm tra Rate Limiter
        $key = strtolower($credentials['email']) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) { // 5 lần thử tối đa trong 1 phút
            $seconds = RateLimiter::availableIn($key);

            // Ném ValidationException với mã lỗi 429 (Too Many Requests)
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // 2. Thử xác thực người dùng
        if (!Auth::attempt($credentials)) {
            // Nếu thất bại, tăng bộ đếm Rate Limiter
            RateLimiter::hit($key);

            // Ném ValidationException với mã lỗi 422 (Unprocessable Entity)
            throw ValidationException::withMessages([
                'email' => __('auth.failed'), // Sử dụng thông báo chuẩn của Laravel
            ]);
        }

        // 3. Nếu xác thực thành công, xóa bộ đếm Rate Limiter
        RateLimiter::clear($key);

        // Lấy thông tin người dùng
        $user = $this->userRepository->findByEmail($credentials['email']);


        // 5. Tạo và trả về token
        $user->tokens()->delete(); // Xóa token cũ để đảm bảo chỉ có 1 session
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
