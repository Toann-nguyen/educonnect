<?php

namespace App\Repositories\Contracts;

use App\Models\Discipline;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

use Illuminate\Support\Collection;

interface DisciplineRepositoryInterface
{
    /**
     * Lấy danh sách kỷ luật với filters và phân trang
     */
    public function getAll(array $filters): LengthAwarePaginator;

    /**
     * Lấy kỷ luật theo ID
     */
    public function findById(int $id): ?Discipline;

    /**
     * Lấy kỷ luật theo student ID
     */
    public function getByStudentId(int $studentId, array $filters): LengthAwarePaginator;

    /**
     * Lấy kỷ luật theo class ID
     */
    public function getByClassId(int $classId, array $filters): LengthAwarePaginator;

    /**
     * Lấy kỷ luật của học sinh (cho student role)
     */
    public function getByStudentUserId(int $userId): LengthAwarePaginator;

    /**
     * Lấy kỷ luật của con (cho parent role)
     */
    public function getByParentUserId(int $parentUserId): LengthAwarePaginator;

    /**
     * Tạo bản ghi kỷ luật mới
     */
    public function create(array $data): Discipline;

    /**
     * Cập nhật bản ghi kỷ luật
     */
    public function update(int $id, array $data): Discipline;

    /**
     * Xóa mềm bản ghi kỷ luật
     */
    public function delete(int $id): bool;

    /**
     * Lấy thống kê kỷ luật
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Lấy top violations
     */
    public function getTopViolations(int $limit = 10, array $filters = []): Collection;

    /**
     * Tìm bản ghi đã xóa mềm
     */
    public function findTrashedById(int $id): ?Discipline;

    /**
     * Khôi phục bản ghi đã xóa
     */
    public function restore(int $id): bool;
}