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
     * Lấy chi tiết 1 role
     */
    public function getRoleDetail(int $roleId)
    {
        $role = $this->roleRepository->getWithPermissions($roleId);

        if (!$role) {
            throw new Exception('Role not found', 404);
        }

        // Add users count
        $usersCount = $this->roleRepository->getUsersCount($roleId);

        return [
            'role' => $role,
            'users_count' => $usersCount,
        ];
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
                'description' => $data['description'] ?? null,
                'is_active' => $data['is_active'] ?? true,
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

            return $role;
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

            // Check constraints
            if (isset($data['is_active']) && !$data['is_active']) {
                $usersCount = $this->roleRepository->getUsersCount($roleId);
                if ($usersCount > 0) {
                    Log::warning("Deactivating role {$roleId} with {$usersCount} active users");
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

            // Log audit
            $this->logAudit('role_deleted', $roleId, 'role', $role->toArray(), null);

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
