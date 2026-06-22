<?php

namespace App\Repositories\Auth;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class AuthRepository implements AuthRepositoryInterface
{
    private const CACHE_PREFIX = 'educonnect:auth:user:';
    private const CACHE_TTL = 300; // 5 phút

    public function __construct(
        private readonly User $model
    ) {}

    public function findByEmail(string $email): ?User
    {
        $cacheKey = self::CACHE_PREFIX . 'email:' . strtolower($email);
        
        // Try Redis cache first
        $cached = Redis::get($cacheKey);
        if ($cached !== null) {
            if ($cached === 'NOT_FOUND') {
                return null;
            }
            return User::find(json_decode($cached, true)['id']);
        }

        // Query database
        $user = $this->model
            ->withoutTrashed()
            ->where('email', $email)
            ->first();

        // Cache result (5 phút)
        if ($user) {
            Redis::setex(
                $cacheKey,
                self::CACHE_TTL,
                json_encode(['id' => $user->id, 'email' => $user->email])
            );
        } else {
            // Cache negative result (2 phút) để chống brute-force
            Redis::setex($cacheKey, 120, 'NOT_FOUND');
        }

        return $user;
    }

    public function create(array $data): User
    {
        $user = $this->model->create($data);
        
        // Invalidate email cache nếu có
        $cacheKey = self::CACHE_PREFIX . 'email:' . strtolower($data['email']);
        Redis::del($cacheKey);
        
        return $user;
    }

    public function update(int $userId, array $data): bool
    {
        $result = $this->model
            ->where('id', $userId)
            ->update($data);

        // Invalidate user cache nếu update thành công
        if ($result) {
            $user = $this->model->find($userId);
            if ($user) {
                $cacheKey = self::CACHE_PREFIX . 'email:' . strtolower($user->email);
                Redis::del($cacheKey);
            }
        }

        return (bool) $result;
    }

    /**
     * Clear cached user data by email
     */
    public function clearCacheByEmail(string $email): void
    {
        $cacheKey = self::CACHE_PREFIX . 'email:' . strtolower($email);
        Redis::del($cacheKey);
    }
}
