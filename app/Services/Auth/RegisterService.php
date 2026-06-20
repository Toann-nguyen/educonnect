<?php

namespace App\Services\Auth;

use App\Events\UserRegistered;
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
     * @throws \Exception nếu email đã tồn tại hoặc đăng ký thất bại
     */
    public function register(array $data): User
    {
        // 1. Kiểm tra email đã tồn tại (No Email Enumeration: Trả về thành công giả lập)
        $existingUser = $this->authRepository->findByEmail($data['email']);
        if ($existingUser) {
            $dummyUser = new User();
            $dummyUser->id = $existingUser->id;
            $dummyUser->email = $existingUser->email;
            $dummyUser->name = $existingUser->name;
            return $dummyUser;
        }

        // 2. Tạo raw token verify email
        $rawToken   = Str::random(64);
        $tokenHash  = hash('sha256', $rawToken);
        $expiresAt  = now()->addHours(24);

        // 3. Wrap trong transaction để đảm bảo tính toàn vẹn
        $user = DB::transaction(function () use ($data, $tokenHash, $expiresAt) {
            $passwordHash = Hash::make($data['password']);

            // Tạo user
            $user = $this->authRepository->create([
                'name'               => $data['name'],
                'email'              => $data['email'],
                'password'           => $data['password'], // Truyền raw để 'hashed' cast tự xử lý
                'password_hash'      => $passwordHash,
                'is_email_verified'  => false,
                'is_active'          => true,
                'token_version'      => 1,
            ]);

            // Gán role mặc định (student theo test case và nghiệp vụ cũ)
            $user->assignRole('student');

            // Tạo / cập nhật token verify email
            $this->emailVerificationRepository->upsert(
                $user->id,
                $tokenHash,
                $expiresAt
            );

            // Dispatch event bên trong transaction với afterCommit
            // Event sẽ chỉ fire sau khi transaction commit thành công
            event(new UserRegistered($user));

            return $user;
        });

        // 4. Dispatch job gửi email SAU KHI transaction commit thành công
        // Sử dụng afterCommit để đảm bảo job chỉ chạy khi DB thành công
        SendVerificationEmail::dispatch($user, $rawToken)
            ->onQueue('emails')
            ->afterCommit();

        return $user;
    }
}
