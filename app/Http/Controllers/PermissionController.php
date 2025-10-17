<?php

namespace App\Http\Controllers;

use App\Services\Interface\PermissionServiceInterface;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\UpdatePermissionRequest as ResourcesUpdatePermissionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Exception;
class PermissionController extends Controller
{
    protected $permissionService;

    public function __construct(PermissionServiceInterface $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    /**
     * GET /api/admin/permissions
     * Lấy danh sách permissions với phân trang
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['search', 'sort_by', 'sort_order', 'per_page']);
            $permissions = $this->permissionService->getAllPermissions($filters);

              return PermissionResource::collection($permissions);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/permissions/{id}
     * Lấy chi tiết một permission
     */
    public function show(int $id): JsonResponse
    {
        try {
            $detail = $this->permissionService->getPermissionDetail($id);

            return response()->json([
                'message' => 'Permission retrieved successfully',
                'data' => $detail
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * POST /api/admin/permissions
     * Tạo permission mới
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $permission = $this->permissionService->createPermission($validated);

            return response()->json([
                'message' => 'Permission created successfully',
                'data' => $permission
            ], 201);
        } catch (\Exception $e) {
          
             return $this->handleException($e);
        }
    }

    /**
     * PUT /api/admin/permissions/{id}
     * Cập nhật permission
     */
    public function update(UpdatePermissionRequest $request, int $id): JsonResponse
    {
        dd(1);
        try {
            $validated = $request->validated();
            $permission = $this->permissionService->updatePermission($id, $validated);

            return response()->json([
                'message' => 'Permission updated successfully',
                'data' => $permission
            ]);
        } catch (\Exception $e) {
           return $this->handleException($e);
        }
    }

    /**
     * DELETE /api/admin/permissions/{id}
     * Xóa permission
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->permissionService->deletePermission($id);

            return response()->json([
                'message' => 'Permission deleted successfully'
            ]);
        } catch (\Exception $e) {
           return $this->handleException($e);
        }
    }

    /**
     * GET /api/admin/permissions/{id}/usage
     * Kiểm tra permission đang được sử dụng
     */
    public function checkUsage(int $id): JsonResponse
    {
        try {
            $usage = $this->permissionService->checkUsage($id);

            return response()->json([
                'message' => 'Permission usage checked successfully',
                'data' => $usage
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/admin/permissions/available/role/{roleId}
     * Lấy permissions chưa gán cho role
     */
    public function availableForRole(int $roleId): JsonResponse
    {
        try {
            $permissions = $this->permissionService->getAvailableForRole($roleId);

            return response()->json([
                'message' => 'Available permissions for role retrieved successfully',
                'data' => $permissions,
                'total' => $permissions->count()
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/admin/permissions/available/user/{userId}
     * Lấy permissions chưa gán cho user
     */
    public function availableForUser(int $userId): JsonResponse
    {
        try {
            $permissions = $this->permissionService->getAvailableForUser($userId);

            return response()->json([
                'message' => 'Available permissions for user retrieved successfully',
                'data' => $permissions,
                'total' => $permissions->count()
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * GET /api/admin/permissions/search
     * Tìm kiếm permissions
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $keyword = $request->query('q');
            $perPage = $request->query('per_page', 15);

            $permissions = $this->permissionService->searchPermissions($keyword, $perPage);

            return response()->json([
                'message' => 'Search completed successfully',
                'data' => $permissions->items(),
                'meta' => [
                    'current_page' => $permissions->currentPage(),
                    'total' => $permissions->total(),
                    'per_page' => $permissions->perPage(),
                    'last_page' => $permissions->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
             return $this->handleException($e);
        }
    }

    /**
     * POST /api/admin/permissions/bulk
     * Tạo nhiều permissions cùng lúc
     */
    public function bulkStore(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'permissions' => 'required|array|min:1',
                'permissions.*.name' => 'required|string|max:100',
                'permissions.*.description' => 'nullable|string|max:500',
                'permissions.*.category' => 'nullable|string|max:50',
                'permissions.*.guard_name' => 'nullable|string|max:50'
            ]);

            $result = $this->permissionService->bulkCreate($validated['permissions']);

            return response()->json([
                'message' => 'Bulk create completed',
                'data' => $result
            ], 201);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /api/admin/permissions/sync-enum
     * Sync permissions từ PermissionEnum
     */
    public function syncFromEnum(): JsonResponse
    {
        try {
            $result = $this->permissionService->syncFromEnum();

            return response()->json([
                'message' => 'Permissions synced from enum successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
             return $this->handleException($e);
        }
    }
    /**
     * Phương thức xử lý lỗi chung cho Controller này.
     * Tránh lặp lại code trong các khối catch.
     */
     private function handleException(Exception $e): JsonResponse
    {
        // Lấy mã lỗi từ Exception, nếu không có hoặc không hợp lệ thì mặc định là 500
        $statusCode = is_int($e->getCode()) && $e->getCode() >= 100 && $e->getCode() <= 599 
                      ? $e->getCode() 
                      : 500;
        
        // Ghi log lỗi để debug
        if ($statusCode === 500) {
            \Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
        
        return response()->json([
            'message' => $statusCode === 500 ? 'An unexpected server error occurred.' : $e->getMessage(),
            'data'=> $e
        ], $statusCode);
    }
}
