<?php

namespace App\Services\Interface;

use \App\Models\User;

use App\Models\Grade;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface GradeServiceInterface
{
    // danh cho student token my-grades
    public function getPersonalGrades(User $user);
    public function getAllGrades(array $filters, User $user): LengthAwarePaginator;
    public function createGrade(array $data, User $creator): Grade;
    public function updateGrade(Grade $grade, array $data, User $updater): Grade;
    public function deleteGrade(Grade $grade, User $deleter): bool;
    public function checkViewPermission(Grade $grade, User $user): void;
    public function getGradesByClass(int $classId, array $filters, User $user): Collection;
    public function getStudentGradeStats(int $studentId, User $user): array;
}
