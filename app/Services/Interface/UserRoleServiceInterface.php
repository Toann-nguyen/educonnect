<?php

namespace App\Services\Interface;

use App\Models\User;
use \Illuminate\Database\Eloquent\Collection;

interface UserRoleServiceInterface
{
    /**
     * Lấy roles của user
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserRoles(int $userId): Collection;

    /**
     * Lấy permissions của user (từ role + direct)
     *
     * @param int $userId
     * @return Collection (collection of permission names)
     */
    public function getUserPermissions(int $userId): Collection;

    /**
     * Lấy direct permissions của user (không từ role)
     *
     * @param int $userId
     * @return Collection
     */
    public function getUserDirectPermissions(int $userId): Collection;

    /**
     * Gán roles cho user
     *
     * @param int $userId
     * @param array $roleNames
     * @param string $mode 'sync' (replace), 'add' (add new)
     * @return bool
     * @throws Exception
     */
    public function assignRolesToUser(int $userId, array $roleNames, string $mode = 'sync'): bool;

    /**
     * Xóa role khỏi user
     *
     * @param int $userId
     * @param string $roleName
     * @return bool
     * @throws Exception
     */
    public function removeRoleFromUser(int $userId, string $roleName): bool;

    /**
     * Gán permission trực tiếp cho user
     *
     * @param int $userId
     * @param array|string $permissions
     * @return bool
     * @throws Exception
     */
    public function givePermissionsToUser($userId, $permissions): bool;

    /**
     * Xóa permission trực tiếp khỏi user
     *
     * @param int $userId
     * @param array|string $permissions
     * @return bool
     * @throws Exception
     */
    public function revokePermissionsFromUser(int $userId, array $permissions): User;

    /**
     * Check user có permission không (OR logic - có 1 trong các permission)
     *
     * @param int $userId
     * @param array|string $permissions
     * @return bool
     */
    public function userCan(int $userId, $permissions): bool;

    /**
     * Check user có tất cả permissions không (AND logic)
     *
     * @param int $userId
     * @param array $permissions
     * @return bool
     */
    public function userCanAll(int $userId, array $permissions): bool;

    /**
     * Check user có role không
     *
     * @param int $userId
     * @param array|string $roles
     * @return bool
     */
    public function userHasRole(int $userId, $roles): bool;

    /**
     * Lấy ma trận quyền của user (roles + direct perms + inherited)
     *
     * @param int $userId
     * @return array
     */
    public function getUserAccessMatrix(int $userId): array;

    
}
