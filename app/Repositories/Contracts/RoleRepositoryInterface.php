<?php

namespace App\Repositories\Contracts;

use Illuminate\Pagination\Paginator;

interface RoleRepositoryInterface
{
    public function paginate(int $perPage = 15, array $filters = []): Paginator;
    public function findById(int $id);
    public function findByName(string $name);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function forceDelete(int $id): bool;
    public function getWithPermissions(int $id);
    public function getUsersCount(int $roleId): int;
}

interface PermissionRepositoryInterface
{
    public function all();
    public function getByCategory(string $category);
    public function findById(int $id);
    public function findByName(string $name);
    public function create(array $data);
    public function update(int $id, array $data);
    public function delete(int $id): bool;
    public function getCategories();
}

interface RolePermissionRepositoryInterface
{
    public function attachPermissionsToRole(int $roleId, array $permissionIds, string $mode = 'sync');
    public function detachPermissionsFromRole(int $roleId, array $permissionIds);
    public function getRolePermissions(int $roleId);
    public function syncUserRoles(int $userId, array $roleNames);
    public function attachRolesToUser(int $userId, array $roleNames);
    public function getUserRoles(int $userId);
    public function getUserPermissions(int $userId);
    public function getUserDirectPermissions(int $userId);
    public function userHasPermission(int $userId, string $permission): bool;
    public function userHasRole(int $userId, string $role): bool;
}
