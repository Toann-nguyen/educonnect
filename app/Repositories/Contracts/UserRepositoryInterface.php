<?php

namespace App\Repositories\Contracts;

interface UserRepositoryInterface
{
    public function all(array $filters = []);
    public function create(array $data);
    public function update(int $id, array $data);

    // xoa mem (dunng soft delete) tuc la tac dong vao delete_at
    public function delete(int $id);

    // reset lai nhung ban ghi ma da delete_at
    public function restore(int $id);

    // xoa cung ban ghi khong dung delete_at
    public function forceDelete(int $id);
    public function find(int $id);
    public function finByEmail(string $email);

    public function paginate(int $perPage = 15, array $filters = []);
}
