<?php

namespace App\Services\Interface;

use App\Models\Role;
use Illuminate\Contracts\Pagination\Paginator;

interface RoleServiceInterface
{
    /**
     * Lấy danh sách roles với pagination
     *
     * @param array $filters ['search', 'is_active', 'sort_by', 'sort_order', 'per_page']
     * @return Paginator
     */
    public function listRoles(array $filters = []);

    /**
     * Lấy chi tiết 1 role kèm permissions
     *
     * @param int $roleId
     * @return array ['role' => Role, 'users_count' => int]
     * @throws Exception
     */
    public function getRoleDetail(int $roleId):?Role;
    /**
     * Tạo role mới
     *
     * @param array $data ['name', 'description', 'is_active']
     * @param array|null $permissionIds
     * @return mixed (Role model)
     * @throws Exception
     */
    public function createRole(array $data, ?array $permissionIds = null);

    /**
     * Cập nhật role
     *
     * @param int $roleId
     * @param array $data ['description', 'is_active']
     * @return mixed (Role model)
     * @throws Exception
     */
    public function updateRole(int $roleId, array $data);

    /**
     * Xóa role (soft delete)
     *
     * @param int $roleId
     * @return bool
     * @throws Exception
     */
    public function deleteRole(int $roleId);

    /**
     * Gán permissions cho role
     *
     * @param int $roleId
     * @param array $permissionIds
     * @param string $mode 'sync' (replace all), 'add' (add new), 'replace' (xóa rồi add)
     * @return bool
     * @throws Exception
     */
    public function assignPermissionsToRole(
        int $roleId,
        array $permissionIds,
        string $mode = 'sync'
    );

    /**
     * Xóa permissions khỏi role
     *
     * @param int $roleId
     * @param array $permissionIds
     * @return bool
     * @throws Exception
     */
    public function removePermissionsFromRole(int $roleId, array $permissionIds);
     
    /**
     * Lấy permissions của role
     */
    public function getRolePermissions(int $roleId);
    
    /**
     * Thêm các permissions mới vào một role.
     *
     * @param int $roleId
     * @param array $permissionIds
     * @return Role
     */
    public function addPermissionsToRole(int $roleId, array $permissionIds): array;

    /**
     * Đồng bộ (thay thế hoàn toàn) permissions của một role.
     *
     * @param int $roleId
     * @param array $permissionIds
     * @return Role
     */
    public function syncPermissionsForRole(int $roleId, array $permissionIds): Role;
    

}
