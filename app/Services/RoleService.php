<?php

namespace App\Services;

use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Repositories\Contracts\RolePermissionRepositoryInterface;
use App\Services\Interface\RoleServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;
use Illuminate\Contracts\Pagination\Paginator;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService implements RoleServiceInterface
{
    protected $roleRepository;
    protected $permissionRepository;
    protected $rolePermissionRepository;

    public function __construct(
        RoleRepositoryInterface $roleRepository,
        PermissionRepositoryInterface $permissionRepository,
        RolePermissionRepositoryInterface $rolePermissionRepository
    ) {
        $this->roleRepository = $roleRepository;
        $this->permissionRepository = $permissionRepository;
        $this->rolePermissionRepository = $rolePermissionRepository;
    }


    /**
     * Lấy danh sách roles với pagination
     */
    public function listRoles(array $filters = [])
    {
        return $this->roleRepository->paginate(
            $filters['per_page'] ?? 15,
            $filters
        );
    }

    /**
     * Thêm các quyền cho một vai trò (role), bỏ qua những quyền (permissions)đã tồn tại.
     * Trả về một mảng chứa kết quả chi tiết để Controller có thể xử lý.
     *
     * @param int $roleId ID của vai trò cần gán quyền.
     * @param array $permissionIds Mảng các ID quyền từ request.
     * @return array Cấu trúc trả về bao gồm:
     *               - 'role': Đối tượng Role đã được cập nhật.
     *               - 'details': Mảng chi tiết về các quyền đã thêm và bị bỏ qua.
     * @throws Exception Nếu không tìm thấy Role.
     */
    public function addPermissionsToRole(int $roleId, array $permissionIds): array
    {
        return DB::transaction(function () use ($roleId, $permissionIds) {
            $role = $this->roleRepository->findById($roleId);
            if (!$role) {
                throw new Exception('Vai trò (Role) không tồn tại.', 404);
            }

            $uniquePermissionIds = array_unique($permissionIds);

            // LOGIC CHECK: Lấy các permission đã tồn tại
            $existingPermissionIds = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->whereIn('permission_id', $uniquePermissionIds)
                ->pluck('permission_id')
                ->all();

            // LOGIC CHECK: Lọc ra các permission mới
            $newPermissionIds = array_diff($uniquePermissionIds, $existingPermissionIds);

            if (!empty($newPermissionIds)) {
                $this->roleRepository->attachPermissions($roleId, $newPermissionIds);
                app()[PermissionRegistrar::class]->forgetCachedPermissions();
            }

            // Tải lại role đã cập nhật
            $updatedRole = $this->roleRepository->getWithPermissions($roleId);

            // Đóng gói tất cả thông tin cần thiết vào một mảng và trả về
            return [
                'role' => $updatedRole,
                'details' => [
                    'added' => array_values($newPermissionIds),
                    'skipped_existing' => $existingPermissionIds,
                ]
            ];
        });
    }


    /**
     * Đồng bộ (thay thế hoàn toàn) permissions của một role.
     */
    public function syncPermissionsForRole(int $roleId, array $permissionIds): Role
    {
        dd(1);
        // Gọi đến RolePermissionRepository để thực hiện
        $role = $this->rolePermissionRepository->syncPermissions($roleId, $permissionIds);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return $role->load('permissions');
    }
    /**
     * Lấy chi tiết 1 role
     */
    public function getRoleDetail(int $roleId): ?Role
    {
        $role = $this->roleRepository->getWithPermissions($roleId);
        if (!$role) {
            throw new Exception('Role not found', 404);
        }

        if (!$role) {
            throw new Exception('Role not found', 404);
        }
        return $role;
    }

    /**
     * Tạo role mới
     */
    public function createRole(array $data, ?array $permissionIds = null)
    {

        return DB::transaction(function () use ($data, $permissionIds) {
            // Validate unique name
            if ($this->roleRepository->findByName($data['name'])) {
                throw new Exception('Role name already exists', 409);
            }

            // Create role
            $role = $this->roleRepository->create([
                'name' => $data['name'],
                'guard_name' => 'web',
            ]);

            // Attach permissions
            if (!empty($permissionIds)) {
                $this->rolePermissionRepository->attachPermissionsToRole(
                    $role->id,
                    $permissionIds
                );
            }

            // Log audit
            $this->logAudit('role_created', $role->id, 'role', null, $role->name);

            // Clear cache
            $this->clearRoleCache();

            return $role->load('permissions');
        });
    }

    /**
     * Cập nhật role
     */
    public function updateRole(int $roleId, array $data)
    {

        return DB::transaction(function () use ($roleId, $data) {
            $role = $this->roleRepository->findById($roleId);
            if (!$role) {
                throw new Exception('Role not found', 404);
            }
            // === THÊM LOGIC KIỂM TRA TRÙNG TÊN KHI CẬP NHẬT ===
            if (isset($data['name'])) {
                // Tìm xem có role nào khác có tên này không
                $existingRole = $this->roleRepository->findByName($data['name']);

                // Nếu tìm thấy một role và ID của nó không phải là ID của role đang sửa
                // thì tức là tên đã bị trùng
                if ($existingRole && $existingRole->id !== $roleId) {
                    throw new Exception('Role name already exists', 409);
                }
            }
            
            // Update
            $oldValues = $role->only(['name', 'description', 'is_active']);
            $role = $this->roleRepository->update($roleId, $data);

            // Log audit
            $this->logAudit('role_updated', $roleId, 'role', $oldValues, $data);

            // Clear cache
            $this->clearRoleCache($roleId);

            return $role;
        });
    }

    /**
     * Xóa role
     */
    public function deleteRole(int $roleId)
    {
        return DB::transaction(function () use ($roleId) {
            $role = $this->roleRepository->findById($roleId);

            if (!$role) {
                throw new Exception('Role not found', 404);
            }

            // Check constraints
            if ($role->name === config('rbac.super_admin_role', 'admin')) {
                throw new Exception('Cannot delete super admin role', 403);
            }

            $usersCount = $this->roleRepository->getUsersCount($roleId);
            if ($usersCount > 0) {
                throw new Exception(
                    "Cannot delete role with {$usersCount} active users",
                    409
                );
            }

            // Soft delete
            $this->roleRepository->delete($roleId);


            // Clear cache
            $this->clearRoleCache();

            return true;
        });
    }





    /**
     * Gán permissions cho role
     */
    public function assignPermissionsToRole(int $roleId, array $permissionIds, string $mode = 'sync')
    {
        return DB::transaction(function () use ($roleId, $permissionIds, $mode) {
            $role = $this->roleRepository->findById($roleId);

            if (!$role) {
                throw new Exception('Role not found', 404);
            }

            // Validate permissions exist
            foreach ($permissionIds as $permId) {
                if (!$this->permissionRepository->findById($permId)) {
                    throw new Exception("Permission ID {$permId} not found", 404);
                }
            }

            // Attach
            $this->rolePermissionRepository->attachPermissionsToRole(
                $roleId,
                $permissionIds,
                $mode
            );

            // Log audit
            $this->logAudit('permissions_assigned_to_role', $roleId, 'role', null, [
                'permission_count' => count($permissionIds),
                'mode' => $mode
            ]);

            // Clear cache
            $this->clearRoleCache($roleId);

            return true;
        });
    }
    /**
     * Lấy permissions của role
     */
    public function getRolePermissions(int $roleId)
    {
        $role = $this->roleRepository->findById($roleId);

        if (!$role) {
            throw new Exception('Role not found', 404);
        }

        return $this->rolePermissionRepository->getRolePermissions($roleId);
    }

    /**
     * Xóa permission khỏi role
     */
    public function removePermissionsFromRole(int $roleId, array $permissionIds)
    {
        return DB::transaction(function () use ($roleId, $permissionIds) {
            $role = $this->roleRepository->findById($roleId);

            if (!$role) {
                throw new Exception('Role not found', 404);
            }

            $this->rolePermissionRepository->detachPermissionsFromRole(
                $roleId,
                $permissionIds
            );

            // Log audit
            $this->logAudit('permissions_removed_from_role', $roleId, 'role', null, [
                'permission_count' => count($permissionIds)
            ]);

            // Clear cache
            $this->clearRoleCache($roleId);

            return true;
        });
    }

    /**
     * Lưu audit log
     */
    protected function logAudit($action, $subjectId, $subjectType, $oldValue, $newValue)
    {
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

    /**
     * Xóa cache
     */
    protected function clearRoleCache(?int $roleId = null)
    {
        Cache::forget('roles:list');
        if ($roleId) {
            Cache::forget("role:{$roleId}:permissions");
        }
    }
}
