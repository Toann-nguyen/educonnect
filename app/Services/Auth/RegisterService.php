<?php

namespace App\Services\Auth;

use App\Events\UserRegistered;
use App\Jobs\SendVerificationEmail;
use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Repositories\Contracts\EmailVerificationRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RegisterService
{
    private const CACHE_PREFIX = 'educonnect:auth:register:';

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
        $email = strtolower($data['email']);
        $cacheKey = self::CACHE_PREFIX . 'email:' . $email;

        // 1. Kiểm tra cache email trùng lặp (5 phút) - giảm tải DB
        $cachedCheck = Redis::get($cacheKey);
        if ($cachedCheck === 'EXISTS') {
            // Trả về dummy user để tránh email enumeration
            $dummyUser = new User();
            $dummyUser->id = 0;
            $dummyUser->email = $email;
            return $dummyUser;
        }

        // 2. Kiểm tra email đã tồn tại trong DB (No Email Enumeration)
        $existingUser = $this->authRepository->findByEmail($email);
        if ($existingUser) {
            // Cache kết quả để giảm DB queries cho các request tiếp theo
            Redis::setex($cacheKey, 300, 'EXISTS');
            
            $dummyUser = new User();
            $dummyUser->id = $existingUser->id;
            $dummyUser->email = $existingUser->email;
            $dummyUser->name = $existingUser->name;
            return $dummyUser;
        }

        // 3. Tạo raw token verify email
        $rawToken   = Str::random(64);
        $tokenHash  = hash('sha256', $rawToken);
        $expiresAt  = now()->addHours(24);

        // 4. Wrap trong transaction để đảm bảo tính toàn vẹn
        $user = DB::transaction(function () use ($data, $email, $tokenHash, $expiresAt) {
            // Tạo password hash TRƯỚC khi insert (giảm thời gian transaction)
            $passwordHash = Hash::make($data['password']);

            // Tạo user
            $user = $this->authRepository->create([
                'name'               => $data['name'],
                'email'              => $email,
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

        // 5. Dispatch job gửi email SAU KHI transaction commit thành công
        // Sử dụng afterCommit để đảm bảo job chỉ chạy khi DB thành công
        SendVerificationEmail::dispatch($user, $rawToken)
            ->onQueue('emails')
            ->afterCommit();

        return $user;
    }
}
