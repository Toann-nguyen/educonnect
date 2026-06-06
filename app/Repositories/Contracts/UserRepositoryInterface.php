<?php

namespace App\Repositories\Contracts;

interface UserRepositoryInterface
{
    public function allUser(array $filters = []);
    public function createUser(array $data);
    public function updateUser(int $id, array $data);

    // xoa mem (dunng soft delete) tuc la tac dong vao delete_at
    public function deleteUser(int $id);

    // reset lai nhung ban ghi ma da delete_at
    public function restoreUser(int $id);

    // xoa cung ban ghi khong dung delete_at
    public function forceDeleteUser(int $id);
    public function find(int $id);
    public function findByEmailUser(string $email);

    public function paginate(int $perPage = 15, array $filters = []);
}
