<?php

namespace App\Services;

use App\Models\User;
use App\Services\Interface\UserServiceInterface;
use Spatie\Permission\Models\Role;

class UserService implements UserServiceInterface
{
    public function getAllUsers(array $filters)
    {
        return User::with('profile', 'roles')
            ->when($filters['role'] ?? null, fn($q, $role) => $q->role($role))
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) =>
                $q->whereHas('profile', fn($p) => $p->where('full_name', 'like', "%{$search}%"))
                    ->orWhere('email', 'like', "%{$search}%")
            )
            ->paginate($filters['per_page'] ?? 15);
    }
    public  function assignRoleToUser(User $user, string $roleName): User
    {
        // Spatie's findByName throws an exception if not found, which is good
        Role::findByName($roleName);

        // syncRoles sẽ xóa các role cũ và chỉ gán role mới này
        $user->syncRoles([$roleName]);

        return $user->load('roles', 'permissions');
    }
    public function removeRoleFromUser(User $user, string $roleName): User
    {
        if ($user->hasRole($roleName)) {
            $user->removeRole($roleName);
        }
        return $user->load('roles', 'permissions');
    }

    public function deactivateUser(User $user): bool
    {
        return $user->delete();
    }

    public function restoreUser(int $id): ?User
    {
        $user = User::onlyTrashed()->find($id);
        if ($user) {
            $user->restore();
            return $user->load('profile', 'roles');
        }
        return null;
    }
}
