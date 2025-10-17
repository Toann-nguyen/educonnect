<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\Paginator;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface
{
    public function paginate(int $perPage = 15, array $filters = []);
    public function findById(int $id);
    public function findByName(string $name);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function forceDelete(int $id): bool;
    public function getWithPermissions(int $id);
    public function getUsersCount(int $roleId): int;
    
      /**
     * Gán thêm các quyền mới cho một vai trò (chỉ thêm, không xóa).
     *
     * @param int $roleId
     * @param array $permissionIds
     * @return bool
     */
    public function attachPermissions(int $roleId, array $permissionIds): bool;
}

interface RolePermissionRepositoryInterface
{ 
    
    /**
     * Đồng bộ hóa (xóa cũ, thêm mới) các quyền cho một vai trò.
     */
    public function syncPermissions(int $roleId, array $permissionIds): Role;

    public function detachPermissionsFromRole(int $roleId, array $permissionIds);

    public function syncUserRoles(int $userId, array $roleNames);

    public function attachRolesToUser(int $userId, array $roleNames);

    public function getUserRoles(int $userId);
    public function getUserPermissions(int $userId);

    public function getUserDirectPermissions(int $userId);

    public function userHasPermission(int $userId, string $permission): bool;

    public function userHasRole(int $userId, string $role): bool;
    /**
     * Gán thêm (chỉ thêm, không xóa) các quyền mới cho một vai trò.
     */
    public function attachPermissions(int $roleId, array $permissionIds): Role;
}
