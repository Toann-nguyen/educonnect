<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Interface\UserServiceInterface;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{

    protected $userService;
    public function __construct(UserServiceInterface $userService)
    {
        $this->userService = $userService;
    }
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
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();
        $user = User::create([
            'email' => $validated['email'],
            'password' => Hash::make('password'), // Mặc định, nên yêu cầu đổi sau lần đăng nhập đầu tiên
        ]);
        $user->profile()->create($validated['profile']);

        foreach ($validated['roles'] as $roleName) {
            $this->userService->assignRoleToUser($user, $roleName);
        }
        if (!empty($validated['permissions'])) {
            $user->givePermissionTo($validated['permissions']);
        }
        return new UserResource($user);
    }
}
