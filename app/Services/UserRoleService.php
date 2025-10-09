<?php

namespace App\Services;

use App\Repositories\Contracts\RolePermissionRepositoryInterface;
// THAY ĐỔI: Sử dụng User Model thay vì DB facade để tận dụng relationship
use App\Models\User;
use App\Services\Interface\UserRoleServiceInterface;
use Illuminate\Database\Eloquent\Collection; // Thêm import
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class UserRoleService implements UserRoleServiceInterface
{
    protected $rolePermissionRepository;

    public function __construct(RolePermissionRepositoryInterface $rolePermissionRepository)
    {
        $this->rolePermissionRepository = $rolePermissionRepository;
    }

    public function getUserRoles(int $userId): Collection
    {
        return $this->rolePermissionRepository->getUserRoles($userId);
    }

    public function getUserPermissions(int $userId): Collection
    {
        return $this->rolePermissionRepository->getUserPermissions($userId);
    }

    public function assignRolesToUser(int $userId, array $roleNames, string $mode = 'sync'): bool
    {
        // ... giữ nguyên logic của bạn ...
        // logic đã tốt, nhưng nên dùng model User để find
        $user = User::find($userId);
        if (!$user) {
            throw new Exception('User not found', 404);
        }
        // ...
        return true;
    }

    public function removeRoleFromUser(int $userId, string $roleName): bool
    {
        // ... giữ nguyên logic của bạn ...
        $user = User::find($userId);
        if (!$user) {
            throw new Exception('User not found', 404);
        }
        // ...
        return true;
    }

    public function userCan(int $userId, $permissions): bool
    {
        // Logic của bạn đã đúng
        // Tuy nhiên, có thể tối ưu hơn bằng cách dùng package spatie
        $user = User::find($userId);
        if (!$user) return false;
        return $user->hasAnyPermission($permissions);
    }

    public function userCanAll(int $userId, array $permissions): bool
    {
        // Logic của bạn đã đúng
        $user = User::find($userId);
        if (!$user) return false;
        return $user->hasAllPermissions($permissions);
    }

    // --- BẮT ĐẦU TRIỂN KHAI CÁC HÀM CÒN THIẾU TỪ INTERFACE ---

    public function getUserDirectPermissions(int $userId): Collection
    {
        $user = User::findOrFail($userId);
        return $user->getDirectPermissions();
    }

    public function givePermissionsToUser($userId, $permissions): bool
    {
        $user = User::findOrFail($userId);
        $user->givePermissionTo($permissions);
        $this->clearUserCache($userId);
        // Log audit
        $this->logAudit('direct_permissions_given_to_user', $userId, 'user', null, ['permissions' => $permissions]);
        return true;
    }

    public function revokePermissionsFromUser($userId, $permissions): bool
    {
        $user = User::findOrFail($userId);
        $user->revokePermissionTo($permissions);
        $this->clearUserCache($userId);
        // Log audit
        $this->logAudit('direct_permissions_revoked_from_user', $userId, 'user', null, ['permissions' => $permissions]);
        return true;
    }

    public function userHasRole(int $userId, $roles): bool
    {
        $user = User::findOrFail($userId);
        return $user->hasRole($roles);
    }

    public function getUserAccessMatrix(int $userId): array
    {
        $user = User::findOrFail($userId);
        $roles = $user->roles;
        $directPermissions = $user->getDirectPermissions();
        $permissionsViaRoles = $user->getPermissionsViaRoles();

        return [
            'user_id' => $userId,
            'roles' => $roles->pluck('name'),
            'permissions' => [
                'direct' => $directPermissions->pluck('name'),
                'via_roles' => $permissionsViaRoles->pluck('name'),
                'all' => $user->getAllPermissions()->pluck('name'),
            ]
        ];
    }

    // ... các hàm protected logAudit, clearUserCache, validateRoleAssignment của bạn giữ nguyên ...
    // ...
    protected function validateRoleAssignment(int $userId, array $roleNames)
    {
        if (in_array(config('rbac.super_admin_role', 'admin'), $roleNames)) {
            $currentUser = auth()->user();
            if (!$currentUser || !$currentUser->hasRole(config('rbac.super_admin_role', 'admin'))) {
                throw new Exception('Only admin can assign admin role', 403);
            }
        }
        //...
    }
    protected function logAudit($action, $subjectId, $subjectType, $oldValue, $newValue)
    { /*...*/
    }
    protected function clearUserCache(int $userId)
    { /*...*/
    }
}
