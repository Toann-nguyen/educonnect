<?php

namespace App\Services\Interface;

use App\Models\User;

interface UserServiceInterface
{
    public function getAllUsers(array $filters);
    public function assignRoleToUser(User $user, string $roleName): User;
    public function removeRoleFromUser(User $user, string $roleName): User;
    public function deactivateUser(User $user): bool;
    public function restoreUser(int $id): ?User;
}
