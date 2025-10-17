<?php

namespace App\Repositories\Eloquent;

use Illuminate\Support\Facades\DB;
use App\Repositories\Contracts\RolePermissionRepositoryInterface;
use Spatie\Permission\Models\Role;
use Exception;

class RolePermissionRepository implements RolePermissionRepositoryInterface
{


      public function syncPermissions(int $roleId, array $permissionIds): Role
    {
        $role = Role::findById($roleId);
        if (!$role) {
            throw new Exception("Role with ID {$roleId} not found.", 404);
        }

        // Spatie's syncPermissions() sẽ thao tác với bảng `role_has_permissions`
        $role->syncPermissions($permissionIds);
        
        return $role;
    }

     public function attachPermissions(int $roleId, array $permissionIds): Role
    {
        $role = Role::findById($roleId);
        if (!$role) {
            throw new Exception("Role with ID {$roleId} not found.", 404);
        }

        // Spatie's givePermissionTo() cũng sẽ thao tác với bảng `role_has_permissions`
        $role->givePermissionTo($permissionIds);

        return $role;
    }    

    /**
     * Thao tác với danh sách permissions của một role.
     */
    public function syncPermissionsForRole(int $roleId, array $permissionIds): Role
    {
        $role = Role::findById($roleId);
        if (!$role) {
            throw new Exception("Role with ID {$roleId} not found.", 404);
        }
        // syncPermissions: Xóa tất cả quyền cũ, chỉ gán danh sách mới.
        $role->syncPermissions($permissionIds);
        return $role;
    }



    public function detachPermissionsFromRole(int $roleId, array $permissionIds)
    {
        DB::table('role_has_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        return true;
    }

    public function getRolePermissions(int $roleId)
    {
        return DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_has_permissions.role_id', $roleId)
            ->select('permissions.*')
            ->get();
    }

    public function syncUserRoles(int $userId, array $roleNames)
    {
        // Lấy role IDs từ names
        $roleIds = DB::table('roles')
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->toArray();

        // Sync (xóa cũ, thêm mới)
        DB::table('model_has_roles')
            ->where('model_id', $userId)
            ->where('model_type', 'App\\Models\\User')
            ->delete();

        foreach ($roleIds as $roleId) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_id' => $userId,
                'model_type' => 'App\\Models\\User',
            ]);
        }

        return true;
    }

    public function attachRolesToUser(int $userId, array $roleNames)
    {
        $roleIds = DB::table('roles')
            ->whereIn('name', $roleNames)
            ->pluck('id')
            ->toArray();

        // Remove duplicates
        $existing = DB::table('model_has_roles')
            ->where('model_id', $userId)
            ->where('model_type', 'App\\Models\\User')
            ->pluck('role_id')
            ->toArray();

        $roleIds = array_diff($roleIds, $existing);

        foreach ($roleIds as $roleId) {
            DB::table('model_has_roles')->insert([
                'role_id' => $roleId,
                'model_id' => $userId,
                'model_type' => 'App\\Models\\User',
            ]);
        }

        return true;
    }

    public function getUserRoles(int $userId)
    {
        return DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->select('roles.*')
            ->get();
    }

    public function getUserPermissions(int $userId)
    {
        // Permissions từ roles
        $rolePermissions = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('model_has_roles', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->select('permissions.*');

        // Permissions trực tiếp
        $directPermissions = DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', 'App\\Models\\User')
            ->select('permissions.*');

        // Union
        return $rolePermissions->union($directPermissions)
            ->distinct()
            ->pluck('name');
    }

    public function getUserDirectPermissions(int $userId)
    {
        return DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', 'App\\Models\\User')
            ->select('permissions.*')
            ->get();
    }

    public function userHasPermission(int $userId, string $permission): bool
    {
        $exists = DB::table('permissions')
            ->join('role_has_permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('model_has_roles', 'role_has_permissions.role_id', '=', 'model_has_roles.role_id')
            ->where('permissions.name', $permission)
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->exists();

        if ($exists) {
            return true;
        }

        // Check direct permission
        return DB::table('permissions')
            ->join('model_has_permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->where('permissions.name', $permission)
            ->where('model_has_permissions.model_id', $userId)
            ->where('model_has_permissions.model_type', 'App\\Models\\User')
            ->exists();
    }

    public function userHasRole(int $userId, string $role): bool
    {
        return DB::table('roles')
            ->join('model_has_roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', $role)
            ->where('model_has_roles.model_id', $userId)
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->exists();
    }
}
