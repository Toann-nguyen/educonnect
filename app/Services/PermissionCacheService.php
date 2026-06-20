<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Redis;

class PermissionCacheService
{
    private const TTL = 300;

    private function key(int $userId): string
    {
        return "user:{$userId}:permissions";
    }

    /**
     * Lấy cả roles + permissions trong 1 lần đọc Redis.
     *
     * @return array{roles: string[], permissions: string[]}
     */
    public function get(int $userId): array
    {
        $cached = Redis::get($this->key($userId));
        if ($cached !== null) {
            return json_decode($cached, true);
        }

        $user = User::with('roles.permissions')->find($userId);

        $data = [
            'roles'       => $user ? $user->roles->pluck('name')->values()->toArray() : [],
            'permissions' => $user ? $user->getAllPermissions()->pluck('name')->values()->toArray() : [],
        ];

        Redis::setex($this->key($userId), self::TTL, json_encode($data));

        return $data;
    }

    public function getPermissions(int $userId): array
    {
        return $this->get($userId)['permissions'] ?? [];
    }

    public function getRoles(int $userId): array
    {
        return $this->get($userId)['roles'] ?? [];
    }

    public function clearUser(int $userId): void
    {
        Redis::del($this->key($userId));
    }

    public function clearAll(): void
    {
        $iterator = null;
        while ($keys = Redis::scan($iterator, ['match' => 'user:*:permissions', 'count' => 100])) {
            if (!empty($keys)) {
                Redis::del($keys);
            }
        }
    }
}
