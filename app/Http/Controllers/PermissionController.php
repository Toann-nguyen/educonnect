<?php

namespace App\Http\Controllers;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Http\Requests\StorePermissionRequest;
use App\Services\Interface\PermissionServiceInterface;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    protected $permissionRepository;

    public function __construct(PermissionServiceInterface $permissionRepository)
    {
        $this->permissionRepository = $permissionRepository;
        $this->middleware('permission:manage_permissions');
    }

    /**
     * GET /api/admin/permissions
     * Lấy tất cả permissions
     */
    public function index(Request $request)
    {
        try {
            $permissions = $this->permissionRepository->all();

            return response()->json([
                'message' => 'Permissions retrieved successfully',
                'data' => $permissions,
                'total' => count($permissions)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/permissions/categories
     * Lấy permissions theo category
     */
    public function categories()
    {
        try {
            $categories = $this->permissionRepository->getCategories();
            $result = [];

            foreach ($categories as $category) {
                $result[$category] = $this->permissionRepository->getByCategory($category);
            }

            return response()->json([
                'message' => 'Permissions grouped by category',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/admin/permissions
     * Tạo permission mới (admin/dev only)
     */
    public function store(StorePermissionRequest $request)
    {
        try {
            $validated = $request->validated();
            $permission = $this->permissionRepository->create($validated);

            return response()->json([
                'message' => 'Permission created successfully',
                'data' => $permission
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PUT /api/admin/permissions/{id}
     * Cập nhật permission
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'description' => 'nullable|string|max:500',
                'category' => 'nullable|string|max:50',
            ]);

            $permission = $this->permissionRepository->update($id, $validated);

            if (!$permission) {
                return response()->json([
                    'message' => 'Permission not found'
                ], 404);
            }

            return response()->json([
                'message' => 'Permission updated successfully',
                'data' => $permission
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * DELETE /api/admin/permissions/{id}
     * Xóa permission
     */
    public function destroy($id)
    {
        try {
            $deleted = $this->permissionRepository->delete($id);

            if (!$deleted) {
                return response()->json([
                    'message' => 'Permission not found'
                ], 404);
            }

            return response()->json([
                'message' => 'Permission deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
