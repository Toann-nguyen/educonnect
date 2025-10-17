<?php

namespace App\Http\Controllers;

use App\Services\UserRoleService;
use App\Http\Requests\AssignRolesToUserRequest;
use Illuminate\Http\Request;
use App\Http\Requests\AssignPermissionsToUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Services\Interface\UserRoleServiceInterface;
use Spatie\Permission\Models\Permission;
use App\Http\Resources\PermissionResource;
use Exception;
class UserRoleController extends Controller
{
    protected $userRoleService;

    public function __construct(UserRoleService $userRoleService)
    {
        $this->userRoleService = $userRoleService;
      }

    /**
     * GET /api/admin/users/{userId}/roles
     * Lấy roles của user
     */
    public function getUserRoles($userId)
    {
        try {
            $roles = $this->userRoleService->getUserRoles($userId);

            return response()->json([
                'message' => 'User roles retrieved successfully',
                'data' => $roles,
                // SỬA LỖI Ở ĐÂY: Sử dụng phương thức ->count() của Collection
                'total' => $roles->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/admin/users/{userId}/permissions
     * Lấy permissions của user (role + direct)
     */
    public function getUserPermissions($userId)
    {
        try {
            $permissions = $this->userRoleService->getUserPermissions($userId);

            return response()->json([
                'message' => 'User permissions retrieved successfully',
                'data' => $permissions,
                // Sử dụng phương thức ->count() của Collection
                'total' => $permissions->count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/admin/users/{userId}/roles
     * Gán roles cho user
     */
    public function assignRoles(AssignRolesToUserRequest $request, $userId)
    {
        try {
            $validated = $request->validated();
            $this->userRoleService->assignRolesToUser(
                $userId,
                $validated['roles'],
                $validated['mode'] ?? 'sync'
            );

            return response()->json([
                'message' => 'Roles assigned successfully',
                'data' => $this->userRoleService->getUserRoles($userId)
            ]);
        } catch (\Exception $e) {
            $statusCode = $e->getCode() ?: 500;
            return response()->json([
                'message' => $e->getMessage()
            ], $statusCode);
        }
    }

    /**
     * DELETE /api/admin/users/{userId}/roles/{roleName}
     * Xóa role khỏi user
     */
     public function removeRole(User $user, Role $role): JsonResponse
    {
        try {
            $updatedUser = $this->userRoleService->removeRoleFromUser($user->id, $role->name);

            return response()->json(new UserResource($updatedUser));

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

     /**
     * DELETE /api/admin/users/{user}/permissions/{permission}
     * Xóa một quyền hạn được gán trực tiếp khỏi người dùng.
     */
    public function removePermission(User $user,Permission  $permission): JsonResponse
    {
        try {
            $this->userRoleService->revokePermissionsFromUser($user->id, [$permission->name]);

            // Lấy lại danh sách các quyền trực tiếp còn lại để trả về
            $directPermissions = $this->userRoleService->getUserDirectPermissions($user->id);

            return response()->json([
                'message' => 'Direct permission revoked successfully.',
                'data' => PermissionResource::collection($directPermissions)
            ]);

        } catch (Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * POST /api/admin/users/{userId}/permissions
     * Gán permissions trực tiếp cho user (không qua role)
     */
    public function assignPermissions(AssignPermissionsToUserRequest $request, int $userId): JsonResponse
    {
        try {
            $validated = $request->validated();
            
            $this->userRoleService->givePermissionsToUser(
                $userId,
                $validated['permissions']
            );

            return response()->json([
                'message' => 'Direct permissions assigned successfully',
                'data' => $this->userRoleService->getUserDirectPermissions($userId)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
   

    /**
     * GET /api/me/roles
     * Lấy roles của user hiện tại
     */
    public function currentUserRoles()
    {
        try {
            $userId = auth()->id();
            $roles = $this->userRoleService->getUserRoles($userId);

            return response()->json([
                'message' => 'Your roles',
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }
    

    /**
     * GET /api/me/permissions
     * Lấy permissions của user hiện tại
     */
    public function currentUserPermissions()
    {
        try {
            $userId = auth()->id();
            $permissions = $this->userRoleService->getUserPermissions($userId);

            return response()->json([
                'message' => 'Your permissions',
                'data' => $permissions
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /api/me/can?permission=xxx&permission=yyy&all=false
     * Check user có quyền không
     */
    public function currentUserCan(Request $request)
    {
        try {
            $permissions = $request->query('permission', []);
            $all = $request->query('all', false) === 'true';

            if (is_string($permissions)) {
                $permissions = [$permissions];
            }

            $userId = auth()->id();

            if ($all) {
                $can = $this->userRoleService->userCanAll($userId, $permissions);
            } else {
                $can = $this->userRoleService->userCan($userId, $permissions);
            }

            $userPermissions = $this->userRoleService->getUserPermissions($userId)->toArray();
            $missing = array_diff($permissions, $userPermissions);

            return response()->json([
                'can' => $can,
                'permissions_requested' => $permissions,
                'permissions_have' => array_values(array_intersect($permissions, $userPermissions)),
                'missing_permissions' => array_values($missing)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 500);
        }
    }

      /**
     * Phương thức xử lý lỗi chung.
     */
    private function handleException(Exception $e): JsonResponse
    {
        $statusCode = is_int($e->getCode()) && $e->getCode() >= 100 && $e->getCode() <= 599 
                      ? $e->getCode() 
                      : 500;
        
        if ($statusCode === 500) {
            \Log::error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }
        
        return response()->json([
            'message' => $statusCode === 500 ? 'An unexpected server error occurred.' : $e->getMessage()
        ], $statusCode);
    }
}
