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

    /**
     * Lấy roles của user
     */
    public function getUserRoles(int $userId): Collection
    {
        // Code gốc của bạn đã đúng
        return $this->rolePermissionRepository->getUserRoles($userId);
    }

    /**
     * Lấy permissions của user (role + direct)
     */
    public function getUserPermissions(int $userId): Collection
    {
        // Code gốc của bạn đã đúng
        $user = User::findOrFail($userId);
        // Dùng hàm của package Spatie để lấy tất cả quyền
        return $user->getAllPermissions();
    }

    /**
     * Gán roles cho user
     */
    public function assignRolesToUser(int $userId, array $roleNames, string $mode = 'sync'): bool
    {
        return DB::transaction(function () use ($userId, $roleNames, $mode) {
            $user = User::findOrFail($userId); // Dùng model thay vì DB facade

            // Logic validate của bạn đã tốt
            $this->validateRoleAssignment($userId, $roleNames);

            // Gán role dùng package Spatie
            if ($mode === 'sync') {
                $user->syncRoles($roleNames);
            } else { // mode 'add'
                $user->assignRole($roleNames);
            }

            // Log audit
            $this->logAudit('roles_assigned_to_user', $userId, 'user', null, [
                'roles' => $roleNames,
                'mode' => $mode
            ]);

            // Clear cache
            $this->clearUserCache($userId);

            return true;
        });
    }

    /**
     * Xóa role khỏi user
     */
    public function removeRoleFromUser(int $userId, string $roleName): bool
    {
        return DB::transaction(function () use ($userId, $roleName) {
            $user = User::findOrFail($userId);
            $user->removeRole($roleName);

            // Log audit
            $this->logAudit('role_removed_from_user', $userId, 'user', ['role' => $roleName], null);

            // Clear cache
            $this->clearUserCache($userId);

            return true;
        });
    }

    /**
     * Check user có permission không
     */
    public function userCan(int $userId, $permissions): bool
    {
        $user = User::findOrFail($userId);
        return $user->hasAnyPermission($permissions);
    }

    /**
     * Check user có tất cả permissions không
     */
    public function userCanAll(int $userId, array $permissions): bool
    {
        $user = User::findOrFail($userId);
        return $user->hasAllPermissions($permissions);
    }

    // --- CÁC PHƯƠNG THỨC CÒN THIẾU ĐƯỢC VIẾT THÊM VÀO ĐÂY ---

    /**
     * Lấy direct permissions của user (không từ role)
     */
    public function getUserDirectPermissions(int $userId): Collection
    {
        $user = User::findOrFail($userId);
        return $user->getDirectPermissions();
    }

    /**
     * Gán permission trực tiếp cho user
     */
    public function givePermissionsToUser($userId, $permissions): bool
    {
        $user = User::findOrFail($userId);
        $user->givePermissionTo($permissions);
        $this->clearUserCache($userId);
        $this->logAudit('direct_permissions_given_to_user', $userId, 'user', null, ['permissions' => $permissions]);
        return true;
    }

    /**
     * Xóa permission trực tiếp khỏi user
     */
    public function revokePermissionsFromUser($userId, $permissions): bool
    {
        $user = User::findOrFail($userId);
        $user->revokePermissionTo($permissions);
        $this->clearUserCache($userId);
        $this->logAudit('direct_permissions_revoked_from_user', $userId, 'user', null, ['permissions' => $permissions]);
        return true;
    }

    /**
     * Check user có role không
     */
    public function userHasRole(int $userId, $roles): bool
    {
        $user = User::findOrFail($userId);
        return $user->hasRole($roles);
    }

    /**
     * Lấy ma trận quyền của user (roles + direct perms + inherited)
     */
    public function getUserAccessMatrix(int $userId): array
    {
        $user = User::findOrFail($userId);

        return [
            'user_id' => $userId,
            'roles' => $user->getRoleNames(),
            'permissions' => [
                'direct' => $user->getDirectPermissions()->pluck('name'),
                'via_roles' => $user->getPermissionsViaRoles()->pluck('name'),
                'all' => $user->getAllPermissions()->pluck('name'),
            ]
        ];
    }

    // --- CÁC HÀM PROTECTED CỦA BẠN GIỮ NGUYÊN ---
    protected function validateRoleAssignment(int $userId, array $roleNames)
    {
        // Code gốc của bạn giữ nguyên
        if (in_array('admin', $roleNames)) {
            $currentUser = auth()->user();
            if (!$currentUser || !$currentUser->hasRole('admin')) {
                throw new Exception('Only admin can assign admin role', 403);
            }
        }
        $conflictRoles = [['student', 'teacher'], ['student', 'admin']];
        foreach ($conflictRoles as $conflict) {
            if (count(array_intersect($roleNames, $conflict)) > 1) {
                throw new Exception('Cannot assign conflicting roles: ' . implode(' and ', $conflict), 422);
            }
        }
    }

    protected function logAudit($action, $subjectId, $subjectType, $oldValue, $newValue)
    {
        // Code gốc của bạn giữ nguyên
        try {
            DB::table('audit_logs')->insert([
                'action' => $action,
                'performer_id' => auth()->id(),
                'subject_id' => $subjectId,
                'subject_type' => $subjectType,
                'old_value' => json_encode($oldValue),
                'new_value' => json_encode($newValue),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Failed to log audit: ' . $e->getMessage());
        }
    }

    protected function clearUserCache(int $userId)
    {
        // Code gốc của bạn giữ nguyên
        Cache::forget("user:{$userId}:roles");
        Cache::forget("user:{$userId}:permissions");
    }
}
