<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\AssignPermissionsRequest;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    protected $roleService;


    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * GET /api/admin/roles
     * Lấy danh sách roles
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['search', 'is_active', 'sort_by', 'sort_order', 'per_page']);

            /** @var \Illuminate\Pagination\LengthAwarePaginator $roles */
            $roles = $this->roleService->listRoles($filters);

            return RoleResource::collection($roles);
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
    public function show(Role $role)
    {
        try {
            // FIX: Check role tồn tại trước khi gọi service
            if (!$role) {
                return response()->json([
                    'message' => 'Role not found'
                ], 404);
            }

            // Service chỉ cần trả về đối tượng Role đã được load sẵn
            $roleWithDetails = $this->roleService->getRoleDetail($role->id);

            // SỬ DỤNG RESOURCE ĐỂ ĐỊNH DẠNG RESPONSE
            return response()->json([
                'message' => 'Role retrieved successfully',
                'data' => new RoleResource($roleWithDetails)
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
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
                'data' =>  new RoleResource($role),
            ], 201);
        } catch (\Exception $e) {
            $statusCode = (int)($e->getCode() ?: 500);
            // Validate status code (phải từ 100-599)
            if ($statusCode < 100 || $statusCode > 599) {
                $statusCode = 500;
            }
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
        dd(1);
        try {
            // FIX: Validate $id không null hoặc không phải int
            if ($id === null || !is_numeric($id) || $id <= 0) {
                return response()->json([
                    'message' => 'Invalid role ID'
                ], 400);
            }

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
            // FIX: Validate $id không null hoặc không phải int
            if ($id === null || !is_numeric($id) || $id <= 0) {
                return response()->json([
                    'message' => 'Invalid role ID'
                ], 400);
            }

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
     * Gán thêm các quyền cho một vai trò.
     * POST /api/admin/roles/{roleId}/permissions
     */
    public function assignPermissions(AssignPermissionsRequest $request, int $roleId): JsonResponse
    {
        try {
            $permissionIds = $request->validated()['permissions'];

            // 1. Gọi Service. $result bây giờ là một MẢNG.
            $result = $this->roleService->addPermissionsToRole($roleId, $permissionIds);
            
            // 2. "Giải nén" mảng kết quả ra các biến để dễ sử dụng
            $updatedRole = $result['role'];
            $details = $result['details'];
            
            // 3. Dùng $details để tạo message thông báo
            $message = 'Yêu cầu đã được xử lý.';
            if (!empty($details['added']) && empty($details['skipped_existing'])) {
                $message = 'Tất cả các quyền đã được gán thành công.';
            } elseif (empty($details['added']) && !empty($details['skipped_existing'])) {
                $message = 'Không có quyền nào được thêm mới vì tất cả đã được gán từ trước.';
            } elseif (!empty($details['added']) && !empty($details['skipped_existing'])) {
                $message = 'Một số quyền đã được gán, một số khác bị bỏ qua vì đã tồn tại.';
            }

            // 4. Trả về JsonResponse hoàn chỉnh
            return response()->json([
                'message' => $message,
                'data' => new RoleResource($updatedRole), // Dùng đối tượng Role để tạo Resource
                'details' => $details,
            ]);

        } catch (Exception $e) {
            $statusCode = is_numeric($e->getCode()) && $e->getCode() >= 400 ? (int)$e->getCode() : 500;
            return response()->json(['message' => $e->getMessage()], $statusCode);
        }
    }
    /**
     * DELETE /api/admin/roles/{id}/permissions/{permissionId}
     * Xóa permission khỏi role
     */
    public function removePermission($roleId, $permissionId)
    {
        try {
            // FIX: Validate $roleId không null hoặc không phải int
            if ($roleId === null || !is_numeric($roleId) || $roleId <= 0) {
                return response()->json([
                    'message' => 'Invalid role ID'
                ], 400);
            }

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

    /**
     * GET /api/admin/roles/{id}/permissions
     * Lấy danh sách permissions của role
     */
    public function getRolePermissions(int $id): JsonResponse
    {
        try {
            // FIX: Vì method signature có type hint int $id, nhưng nếu route param null, PHP sẽ error sớm. Thêm check an toàn
            if ($id === null || $id <= 0) {
                return response()->json([
                    'message' => 'Invalid role ID'
                ], 400);
            }

            $permissions = $this->roleService->getRolePermissions($id);

            return response()->json([
                'message' => 'Role permissions retrieved successfully',
                'data' => $permissions,
                'total' => $permissions->count()
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }
}
