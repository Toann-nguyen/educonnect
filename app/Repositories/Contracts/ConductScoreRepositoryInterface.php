<?php

namespace App\Repositories\Contracts;

use App\Models\StudentConductScore;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ConductScoreRepositoryInterface
{
    /**
     * Lấy điểm hạnh kiểm theo student user ID (for student role)
     */
    public function getByStudentUserId(int $userId, array $filters): LengthAwarePaginator;

    /**
     * Lấy điểm hạnh kiểm theo parent user ID (for parent role)
     */
    public function getByParentUserId(int $parentUserId, array $filters): LengthAwarePaginator;

    /**
     * Lấy điểm hạnh kiểm theo class ID
     */
    public function getByClassId(int $classId, array $filters): LengthAwarePaginator;

    /**
     * Tìm điểm hạnh kiểm cụ thể
     */
    public function findByStudentSemester(int $studentId, int $semester, int $academicYearId): ?StudentConductScore;

    /**
     * Tạo hoặc cập nhật điểm hạnh kiểm
     */
    public function createOrUpdate(array $data): StudentConductScore;

    /**
     * Cập nhật điểm hạnh kiểm
     */
    public function update(int $id, array $data): StudentConductScore;

    /**
     * Xóa điểm hạnh kiểm
     */
    public function delete(int $id): bool;

    /**
     * Lấy điểm hạnh kiểm của tất cả học sinh trong lớp theo semester
     */
    public function getClassConductScores(int $classId, int $semester, int $academicYearId): Collection;

    /**
     * Lấy tất cả conduct scores của một học sinh (có filter tùy chọn)
     */
    public function findAllByStudent(
        int $studentId,
        ?int $semester = null,
        ?int $academicYearId = null
    );
}
