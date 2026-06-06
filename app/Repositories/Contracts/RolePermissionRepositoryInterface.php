<?php

namespace App\Repositories\Contracts;

use Exception;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

interface RolePermissionRepositoryInterface

{
    /**
     *  trong trường hợp function gọi thêm permission với role trong bảng role_has_permissions 
     *  Trong trường hợp muốn update luôn 
     */
   
    public function syncPermissions(int $roleId, array $permissionIds): Role;
    

    public function attachPermissions(int $roleId, array $permissionIds): Role;


    public function detachPermissionsFromRole(int $roleId, array $permissionIds);

    public function syncUserRoles(int $userId, array $roleNames);

    public function attachRolesToUser(int $userId, array $roleNames);

    public function getUserRoles(int $userId);
    public function getUserPermissions(int $userId);

    public function getUserDirectPermissions(int $userId);

    public function userHasPermission(int $userId, string $permission): bool;

    public function userHasRole(int $userId, string $role): bool;
}
