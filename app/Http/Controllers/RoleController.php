<?php

namespace App\Http\Controllers;

use App\Services\RoleService;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\AssignPermissionsRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    protected $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;

        // Middleware: check permission
        $this->middleware('permission:manage_roles');
    }

    /**
     * GET /api/admin/roles
     * Lấy danh sách roles
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['search', 'is_active', 'sort_by', 'sort_order', 'per_page']);
            $roles = $this->roleService->listRoles($filters);

            return response()->json([
                'message' => 'Roles retrieved successfully',
                'data' => $roles->items(),
                'meta' => [
                    'current_page' => $roles->currentPage(),
                    'total' => $roles->total(),
                    'per_page' => $roles->perPage(),
                    'last_page' => $roles->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve roles',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/roles/{id}
     * Lấy chi tiết 1 role
     */
    public function show($id)
    {
        try {
            $roleDetail = $this->roleService->getRoleDetail($id);

            return response()->json([
                'message' => 'Role retrieved successfully',
                'data' => $roleDetail
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * POST /api/admin/roles
     * Tạo role mới
     */
    public function store(StoreRoleRequest $request)
    {
        try {
            $validated = $request->validated();
            $role = $this->roleService->createRole(
                [
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                ],
                $validated['permissions'] ?? null
            );

            return response()->json([
                'message' => 'Role created successfully',
                'data' => $role
            ], 201);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * PUT /api/admin/roles/{id}
     * Cập nhật role
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        try {
            $validated = $request->validated();
            $role = $this->roleService->updateRole($id, $validated);

            return response()->json([
                'message' => 'Role updated successfully',
                'data' => $role
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * DELETE /api/admin/roles/{id}
     * Xóa role
     */
    public function destroy($id)
    {
        try {
            $this->roleService->deleteRole($id);

            return response()->json([
                'message' => 'Role deleted successfully'
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * POST /api/admin/roles/{id}/permissions
     * Gán permissions cho role
     */
    public function assignPermissions(AssignPermissionsRequest $request, $id)
    {
        try {
            $validated = $request->validated();
            $this->roleService->assignPermissionsToRole(
                $id,
                $validated['permissions'],
                $validated['mode'] ?? 'sync'
            );

            return response()->json([
                'message' => 'Permissions assigned successfully'
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * DELETE /api/admin/roles/{id}/permissions/{permissionId}
     * Xóa permission khỏi role
     */
    public function removePermission($roleId, $permissionId)
    {
        try {
            $this->roleService->removePermissionsFromRole($roleId, [$permissionId]);

            return response()->json([
                'message' => 'Permission removed successfully'
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}
