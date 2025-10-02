<?php

namespace App\Services\Interface;

use App\Models\StudentConductScore;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConductScoreServiceInterface
{
    /**
     * Lấy điểm hạnh kiểm của học sinh/con
     */
    public function getMyConductScores(User $user, array $filters): LengthAwarePaginator;

    /**
     * Lấy điểm hạnh kiểm theo lớp
     */
    public function getConductScoresByClass(int $classId, array $filters): LengthAwarePaginator;

    /**
     * Lấy điểm hạnh kiểm của một học sinh
     */
    public function getStudentConductScore(int $studentId, int $semester, int $academicYearId): ?StudentConductScore;

    /**
     * Cập nhật/Tạo điểm hạnh kiểm
     */
    public function updateConductScore(int $studentId, int $semester, int $academicYearId, array $data): StudentConductScore;

    /**
     * Phê duyệt điểm hạnh kiểm
     */
    public function approveConductScore(StudentConductScore $conductScore, User $approver): StudentConductScore;

    /**
     * Tính toán lại điểm hạnh kiểm dựa trên disciplines
     */
    public function recalculateConductScore(int $studentId, int $semester, int $academicYearId): StudentConductScore;
}
