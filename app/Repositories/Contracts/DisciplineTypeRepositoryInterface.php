<?php

namespace App\Repositories\Contracts;

use App\Models\DisciplineType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DisciplineTypeRepositoryInterface
{
    /**
     * Lấy danh sách loại vi phạm với filters
     */
    public function getAll(array $filters): LengthAwarePaginator;

    /**
     * Lấy tất cả loại vi phạm active (không phân trang)
     */
    public function getAllActive(): Collection;

    /**
     * Tìm loại vi phạm theo ID
     */
    public function findById(int $id): ?DisciplineType;

    /**
     * Tìm loại vi phạm theo code
     */
    public function findByCode(string $code): ?DisciplineType;

    /**
     * Tạo loại vi phạm mới
     */
    public function create(array $data): DisciplineType;

    /**
     * Cập nhật loại vi phạm
     */
    public function update(int $id, array $data): DisciplineType;

    /**
     * Xóa mềm loại vi phạm
     */
    public function delete(int $id): bool;

    /**
     * Tìm bản ghi đã xóa
     */
    public function findTrashedById(int $id): ?DisciplineType;

    /**
     * Khôi phục loại vi phạm
     */
    public function restore(int $id): bool;
}
