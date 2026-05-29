<?php

namespace App\Services\Auth;

use App\Jobs\SendVerificationEmail;
use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RegisterService
{
    public function __construct(
        private readonly AuthRepositoryInterface              $authRepository,
        private readonly EmailVerificationRepositoryInterface $emailVerificationRepository,
    ) {}

    /**
     * Đăng ký tài khoản mới
     *
     * @throws \Exception nếu email đã tồn tại
     */
    public function register(array $data): User
    {
        // 1. Kiểm tra email đã tồn tại
        if ($this->authRepository->findByEmail($data['email'])) {
            throw new \Exception('Email already registered.', 409);
        }

        // 2. Tạo raw token verify email
        $rawToken   = Str::random(64);
        $tokenHash  = hash('sha256', $rawToken);
        $expiresAt  = now()->addHours(24);

        // 3. Wrap trong transaction để đảm bảo tính toàn vẹn
        $user = DB::transaction(function () use ($data, $tokenHash, $expiresAt) {
            // Tạo user
            $user = $this->authRepository->create([
                'name'               => $data['name'],
                'email'              => $data['email'],
                'password_hash'      => Hash::make($data['password'], ['rounds' => 12]),
                'is_email_verified'  => false,
                'is_active'          => true,
                'token_version'      => 1,
            ]);

            // Gán role mặc định
            $user->assignRole('user');

            // Tạo / cập nhật token verify email
            $this->emailVerificationRepository->upsert(
                $user->id,
                $tokenHash,
                $expiresAt
            );

            return $user;
        });

        // 4. Dispatch job gửi email (ngoài transaction, không block response)
        SendVerificationEmail::dispatch($user, $rawToken)
            ->onQueue('emails');

        return $user;
    }
}
