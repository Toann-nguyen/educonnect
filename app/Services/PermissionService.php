<?php

use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\Interface\PermissionServiceInterface;
use Illuminate\Database\Eloquent\Collection;

class PermissionService implements PermissionServiceInterface
{
    protected $permissionRepository;

    public function __construct(PermissionRepositoryInterface $permissionRepository)
    {
        $this->permissionRepository = $permissionRepository;
    }

    /**
     * Lấy tất cả permissions
     */
    public function getAllPermissions(): Collection
    {
        return $this->permissionRepository->all(['id', 'name', 'description', 'category']);
    }

    /**
     * Lấy permissions theo category
     */
    public function getPermissionsByCategory(string $category): Collection
    {
        // Giả sử repository có hàm findWhere
        return $this->permissionRepository->findWhere(['category' => $category]);
    }

    /**
     * Lấy permissions groupby category
     */
    public function getPermissionsGrouped(): array
    {
        return $this->getAllPermissions()->groupBy('category')->toArray();
    }

    /**
     * Lấy chi tiết 1 permission
     */
    public function getPermissionDetail(int $id)
    {
        $permission = $this->permissionRepository->findById($id);
        if (!$permission) {
            throw new Exception('Permission not found', 404);
        }
        return $permission;
    }

    /**
     * Tạo permission mới
     */
    public function createPermission(array $data)
    {
        if ($this->permissionRepository->findByName($data['name'])) {
            throw new Exception('Permission name already exists', 409);
        }
        return $this->permissionRepository->create($data);
    }

    /**
     * Cập nhật permission
     */
    public function updatePermission(int $id, array $data)
    {
        $this->getPermissionDetail($id); // Check for existence before updating
        return $this->permissionRepository->update($id, $data);
    }

    /**
     * Xóa permission
     */
    public function deletePermission(int $id): bool
    {
        $this->getPermissionDetail($id); // Check for existence before deleting
        // Cần thêm logic kiểm tra permission này có đang được role nào sử dụng không
        return $this->permissionRepository->delete($id);
    }

    /**
     * Lấy danh sách categories
     */
    public function getCategories(): Collection
    {
        // Giả sử repository có hàm này để lấy các category không trùng lặp
        return $this->permissionRepository->getDistinctCategories();
    }
}
