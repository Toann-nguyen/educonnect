<?php

namespace App\Repositories\Auth;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function __construct(
        private readonly User $model
    ) {}

    public function findByEmail(string $email): ?User
    {
        return $this->model
            ->withoutTrashed()
            ->where('email', $email)
            ->first();
    }

    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    public function update(int $userId, array $data): bool
    {
        return (bool) $this->model
            ->where('id', $userId)
            ->update($data);
    }
}
