<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PermissionRepositoryInterface
{
 
    /**
     * Lấy permissions có phân trang
     */
    public function all(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Tìm permission theo ID
     */
    public function findById(int $id);

    /**
     * Tìm permission theo name
     */
    public function findByName(string $name);

    /**
     * Tạo permission mới
     */
    public function create(array $data);

    /**
     * Cập nhật permission
     */
    public function update(int $id, array $data);

    /**
     * Xóa permission
     */
    public function delete(int $id): bool;

    /**
     * Kiểm tra permission đang được sử dụng
     */
    public function getUsageCount(int $id): array;
}