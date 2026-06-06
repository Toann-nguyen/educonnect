<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RolePermissionService
{
    const CACHE_KEY_ROLES = 'rbac:roles:';
    const CACHE_TTL = 3600; // 1 hour

    public function initializeRolesAndPermissions(): void
    {
        $this->clearCache();

        foreach (PermissionEnum::cases() as $permission) {
            Permission::firstOrCreate(['name' => $permission->value]);
        }

        $config = config('rbac.roles');
        foreach ($config as $roleName => $roleData) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if ($roleData['all_permissions'] ?? false) {
                $role->syncPermissions(Permission::all());
            } elseif (!empty($roleData['permissions'])) {
                $permissions = collect($roleData['permissions'])
                    ->map(fn($perm) => Permission::where('name', $perm)->first())
                    ->filter()
                    ->all();
                $role->syncPermissions($permissions);
            }
        }

        $this->clearCache();
    }

    public function assignRoleToUser($user, string|array $roles): void
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }
        $user->syncRoles($roles);
        $this->clearUserCache($user->id);
    }

    public function givePermissionToUser($user, string|array $permissions): void
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        $user->givePermissionTo($permissions);
        $this->clearUserCache($user->id);
    }

    public function getUserPermissions($user): Collection
    {
        $cacheKey = self::CACHE_KEY_ROLES . 'user_permissions:' . $user->id;
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $user->getAllPermissions()->pluck('name');
        });
    }

    public function getUserRoles($user): Collection
    {
        $cacheKey = self::CACHE_KEY_ROLES . 'user_roles:' . $user->id;
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return $user->roles->pluck('name');
        });
    }

    public function can($user, string|array $permissions): bool
    {
        if (is_string($permissions)) {
            $permissions = [$permissions];
        }
        $userPermissions = $this->getUserPermissions($user);
        foreach ($permissions as $permission) {
            if ($userPermissions->contains($permission)) {
                return true;
            }
        }
        return false;
    }

    public function canAll($user, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($user);
        foreach ($permissions as $permission) {
            if (!$userPermissions->contains($permission)) {
                return false;
            }
        }
        return true;
    }

    public function hasRole($user, string|array $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }
        $userRoles = $this->getUserRoles($user);
        foreach ($roles as $role) {
            if ($userRoles->contains($role)) {
                return true;
            }
        }
        return false;
    }

    public function getRoleConfig(string $roleName): ?array
    {
        return config('rbac.roles.' . $roleName);
    }

    public function roleExists(string $roleName): bool
    {
        return in_array($roleName, array_keys(config('rbac.roles', [])));
    }

    public function clearCache(): void
    {
        Cache::flush();
    }

    public function clearUserCache(int $userId): void
    {
        Cache::forget(self::CACHE_KEY_ROLES . 'user_permissions:' . $userId);
        Cache::forget(self::CACHE_KEY_ROLES . 'user_roles:' . $userId);
    }
}


