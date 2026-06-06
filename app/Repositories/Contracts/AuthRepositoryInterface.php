<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface AuthRepositoryInterface
{
    /**
     * Tìm user theo email (chưa bị soft delete, không bị deactivate check ở service)
     */
    public function findByEmail(string $email): ?User;

    /**
     * Tạo user mới
     */
    public function create(array $data): User;

    /**
     * Cập nhật user theo id
     */
    public function update(int $userId, array $data): bool;
}
