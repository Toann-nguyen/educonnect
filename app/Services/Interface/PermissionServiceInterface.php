<?php

namespace App\Services\Interface;

use Illuminate\Database\Eloquent\Collection;

interface PermissionServiceInterface
{
    /**
     * Lấy tất cả permissions
     *
     * @return Collection
     */
    public function getAllPermissions(): Collection;

    /**
     * Lấy permissions theo category
     *
     * @param string $category
     * @return Collection
     */
    public function getPermissionsByCategory(string $category): Collection;

    /**
     * Lấy permissions groupby category
     *
     * @return array ['category_name' => [permissions]]
     */
    public function getPermissionsGrouped(): array;

    /**
     * Lấy chi tiết 1 permission
     *
     * @param int $id
     * @return mixed (Permission model)
     * @throws Exception
     */
    public function getPermissionDetail(int $id);

    /**
     * Tạo permission mới
     *
     * @param array $data ['name', 'description', 'category']
     * @return mixed (Permission model)
     * @throws Exception
     */
    public function createPermission(array $data);

    /**
     * Cập nhật permission
     *
     * @param int $id
     * @param array $data ['description', 'category']
     * @return mixed (Permission model)
     * @throws Exception
     */
    public function updatePermission(int $id, array $data);

    /**
     * Xóa permission
     *
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function deletePermission(int $id): bool;

    /**
     * Lấy danh sách categories
     *
     * @return Collection
     */
    public function getCategories(): Collection;
}
