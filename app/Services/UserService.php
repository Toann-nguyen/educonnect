<?php

namespace App\Services;

use App\Models\User;
use App\Services\Interface\UserServiceInterface;
use Exception;
use Log;
use Spatie\Permission\Models\Role;

class UserService implements UserServiceInterface
{
    public function getAllUsers(array $filters)
    {
        return User::with('profile', 'roles')
            ->when($filters['roles'] ?? null, function ($q) use ($filters) {
                $q->whereHas('roles', fn($q) => $q->whereIn('name', $filters['roles']));
            })
            ->when(
                $filters['search'] ?? null,
                fn($q, $search) =>
                $q->whereHas('profile', fn($p) => $p->where('full_name', 'like', "%{$search}%"))
                    ->orWhere('email', 'like', "%{$search}%")
            )
            ->paginate($filters['per_page'] ?? 15);
    }
    public  function assignRoleToUser(User $user,  $roleName = []): User
    {
        if (is_array($roleName)) {
            $user->syncRoles($roleName); // Gán nhiều role nếu là mảng
        } else {
            $user->syncRoles([$roleName]); // Gán một role nếu là string
        }
        return $user->load('roles', 'permissions');
    }
    public function removeRoleFromUser(User $user, string $roleName): User
    {
        if ($user->hasRole($roleName)) {
            $user->removeRole($roleName);
        }
        return $user->load('roles', 'permissions');
    }
    // delete mem soft delete

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
    public function updateUser(User $user, array $data): ?User
    {
        try {
            // Kiểm tra và cập nhật các trường cơ bản
            if (!empty($data)) {
                $user->fill($data);
                $user->save();
            }

            // Cập nhật profile nếu có và là mảng hợp lệ
            if (isset($data['profile']) && is_array($data['profile'])) {
                $user->profile()->update($data['profile']);
            } elseif (isset($data['profile'])) {
                throw new \InvalidArgumentException('Profile data must be an array');
            }

            // Cập nhật role nếu có
            if (isset($data['role'])) {
                $this->assignRoleToUser($user, $data['role']);
            }

            return $user->load('profile', 'roles', 'permissions');
        } catch (Exception $e) {
            // Log the exception or handle it as needed
            Log::info('cant not update user ' . $e->getMessage());
            return null;
        }
    }
}
