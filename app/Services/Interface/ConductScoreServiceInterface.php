<?php

namespace App\Services\Interface;

use App\Models\StudentConductScore;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;


interface ConductScoreServiceInterface
{
    /**
     * Lấy conduct scores của user hiện tại (Student/Parent)
     */
    public function getMyConductScores(
        User $user,
        ?int $semester = null,
        ?int $academicYearId = null
    ): Collection;
    public function recalculateConductScores(
        int $semester,
        int $academicYearId,
        ?int $classId = null,
        ?int $studentId = null
    ): array;

    /**
     * Lấy conduct scores của một lớp
     */
    public function getClassConductScores(
        int $classId,
        User $user,
        ?int $semester = null,
        ?int $academicYearId = null
    ): Collection;

    /**
     * Lấy 1 conduct score cụ thể (theo semester và năm học)
     */
    public function getStudentConductScore(
        int $studentId,
        int $semester,
        int $academicYearId
    ): ?StudentConductScore;

    /**
     * Lấy TẤT CẢ conduct scores của một học sinh
     */
    public function getAllStudentConductScores(
        int $studentId,
        ?int $semester = null,
        ?int $academicYearId = null
    ): Collection;

    /**
     * Cập nhật conduct score
     */
    public function updateConductScore(
        int $studentId,
        int $semester,
        int $academicYearId,
        array $data
    ): StudentConductScore;

    /**
     * Phê duyệt conduct score
     */
    public function approveConductScore($conductScoreId);

    /**
     * Tính lại conduct score từ discipline records
     */
    public function recalculateConductScore(
        int $studentId,
        int $semester,
        int $academicYearId
    ): StudentConductScore;
}
