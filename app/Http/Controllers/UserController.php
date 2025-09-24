<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Interface\UserServiceInterface;
use Spatie\Permission\Models\Role;
use Illuminate\Http\Request;

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
    {
        return new UserResource($user->load('profile', 'roles', 'permissions'));
    }
    public function update(AssignRoleRequest $request, User $user)
    {
        // Gộp assignRole và removeRole vào hàm update cho đúng chuẩn RESTful
        $updatedUser = $this->userService->assignRoleToUser($user, $request->validated()['role']);
        return new UserResource($updatedUser);
    }
    public function destroy(User $user)
    {
        $this->userService->deleteUser($user);
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
}
