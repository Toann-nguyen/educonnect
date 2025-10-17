<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\StoreUserByAdminRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Interface\UserServiceInterface;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    protected $userService;
    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }
    /**
     * @OA\Get(
     *     path="/api/admin/users",
     *     summary="Danh sách người dùng (có phân trang, role, permissions, profile)",
     *     description="Trả về danh sách người dùng dạng phân trang cùng với thông tin chi tiết (profile, roles, permissions).",
     *     tags={"Users"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Số trang hiện tại",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lấy danh sách người dùng thành công",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="email", type="string", example="admin@educonnect.com"),
     *                     @OA\Property(
     *                         property="profile",
     *                         type="object",
     *                         @OA\Property(property="full_name", type="string", example="Admin User"),
     *                         @OA\Property(property="phone_number", type="string", example="385-678-0558"),
     *                         @OA\Property(property="birthday", type="string", example="2001-03-26"),
     *                         @OA\Property(property="gender", type="integer", example=1),
     *                         @OA\Property(property="address", type="string", example="42827 Nannie Loaf, FL 79652-7986"),
     *                         @OA\Property(property="avatar", type="string", example="avatars/default.png")
     *                     ),
     *                     @OA\Property(
     *                         property="roles",
     *                         type="array",
     *                         @OA\Items(type="string", example="admin")
     *                     ),
     *                     @OA\Property(
     *                         property="permissions",
     *                         type="array",
     *                         @OA\Items(type="string", example="manage_users")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", example="2025-10-02T10:27:06.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", example="2025-10-02T10:27:06.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="links",
     *                 type="object",
     *                 @OA\Property(property="first", type="string", example="http://127.0.0.1:8000/api/admin/users?page=1"),
     *                 @OA\Property(property="last", type="string", example="http://127.0.0.1:8000/api/admin/users?page=44"),
     *                 @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                 @OA\Property(property="next", type="string", example="http://127.0.0.1:8000/api/admin/users?page=2")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=44),
     *                 @OA\Property(property="path", type="string", example="http://127.0.0.1:8000/api/admin/users"),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="to", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=646)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Không có quyền truy cập hoặc chưa đăng nhập"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $users = $this->userService->getAllUsers($request->only(['role', 'search', 'per_page']));
        return UserResource::collection($users);
    }
    public function show(User $user)
    {   // check roles xem la student , teacher , admin . Neu la student thi khong hien thi roles , permissions
        // neu roles == admin thi tra toan bo thong tin roi sau do moi cho tung role do update theo nhung truong da hien thi
        $authUser = Auth::user();
        $roles = $authUser->roles->pluck('name')->toArray();

        // Kiểm tra role của user hiện tại
        if (in_array('student', $roles)) {
            // Nếu là student, không hiển thị roles và permissions
            return new UserResource($user->load('profile'));
        } elseif (in_array('admin', $roles)) {
            // Nếu là admin, hiển thị toàn bộ thông tin
            return new UserResource($user->load('profile', 'roles', 'permissions'));
        } else {
            // Nếu là teacher hoặc role khác, hiển thị một phần (ví dụ: profile và roles)
            return new UserResource($user->load('profile', 'roles'));
        }
    }
    public function update(AssignRoleRequest $request, User $user)
    {
        // Gộp assignRole và removeRole vào hàm update cho đúng chuẩn RESTful
        $authUser = Auth::user();
        $roles = $authUser->roles->pluck('name')->toArray();
        $validated = $request->validated();

        // Gán role (hỗ trợ mảng roles hoặc role đơn lẻ)
        if (isset($validated['roles']) && is_array($validated['roles'])) {
            foreach ($validated['roles'] as $roleName) {
                $this->userService->assignRoleToUser($user, $roleName);
            }
        } elseif (isset($validated['role'])) {
            $this->userService->assignRoleToUser($user, $validated['role']);
        }

        // Chuẩn bị dữ liệu cập nhật dựa trên role
        $updateData = $validated;
        if (in_array('admin', $roles)) {
            // Admin được cập nhật toàn bộ thông tin
            $updatedUser = $this->userService->updateUser($user, $updateData);
        } else {
            // Teacher hoặc student chỉ cập nhật một số trường nhất định
            $allowedFields = ['email', 'profile.full_name', 'profile.phone_number'];
            $updateData = array_filter($validated, function ($key) use ($allowedFields) {
                return in_array($key, $allowedFields) || strpos($key, 'profile.') === 0;
            }, ARRAY_FILTER_USE_KEY);

            $updatedUser = $this->userService->updateUser($user, $updateData);
        }

        if ($updatedUser) {
            return new UserResource($updatedUser);
        }

        Log::warning('Failed to update user with ID: ' . $user->id);
        return response()->json(['message' => 'Failed to update user'], 500);
    }
    //delete mem user
    public function destroy(User $user)
    {
        $this->userService->deactivateUser($user);
        return response()->json(['message' => 'User deleted successfully.'], 200);
    }
    public function restore($id)
    {
        $user = $this->userService->restoreUser($id);
        return $user
            ? new UserResource($user)
            : response()->json([
                'message' => 'User not found or not deleted.',
            ], 404);
    }

      /**
     * Store a newly created resource in storage (by Admin).
     *
     * @param StoreUserByAdminRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreUserByAdminRequest $request)
    {
        try {
            $user = $this->userService->createUserByAdmin($request->validated());

            return response()->json([
                'message' => 'User created successfully.',
                'data' => new UserResource($user),
            ], 201);

        } catch (\Exception $e) {
            return $this->handleException($e); 
        }
    }
}
