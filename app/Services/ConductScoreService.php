<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Discipline;
use App\Models\StudentConductScore;
use App\Models\User;
use App\Services\Interface\ConductScoreServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConductScoreService implements ConductScoreServiceInterface
{
    public function getMyConductScores(User $user, array $filters): LengthAwarePaginator
    {
        $query = StudentConductScore::with(['student.user.profile', 'academicYear']);

        if ($user->hasRole('student')) {
            $query->whereHas('student', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->hasRole('parent')) {
            $query->whereHas('student.guardians', function ($q) use ($user) {
                $q->where('guardian_user_id', $user->id);
            });
        }

        if (isset($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        return $query->orderBy('academic_year_id', 'desc')
            ->orderBy('semester', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getConductScoresByClass(int $classId, array $filters): LengthAwarePaginator
    {
        $query = StudentConductScore::with(['student.user.profile'])
            ->whereHas('student', function ($q) use ($classId) {
                $q->where('class_id', $classId);
            });

        if (isset($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['conduct_grade'])) {
            $query->where('conduct_grade', $filters['conduct_grade']);
        }

        return $query->orderBy('conduct_grade')
            ->orderBy('total_penalty_points')
            ->paginate($filters['per_page'] ?? 50);
    }

    public function getStudentConductScore(int $studentId, int $semester, int $academicYearId): ?StudentConductScore
    {
        return StudentConductScore::where('student_id', $studentId)
            ->where('semester', $semester)
            ->where('academic_year_id', $academicYearId)
            ->first();
    }

    public function updateConductScore(int $studentId, int $semester, int $academicYearId, array $data): StudentConductScore
    {
        return DB::transaction(function () use ($studentId, $semester, $academicYearId, $data) {
            $conductScore = StudentConductScore::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'semester' => $semester,
                    'academic_year_id' => $academicYearId,
                ],
                $data
            );

            return $conductScore->fresh();
        });
    }

    public function approveConductScore(StudentConductScore $conductScore, User $approver): StudentConductScore
    {
        $conductScore->update([
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        return $conductScore->fresh();
    }

    public function recalculateConductScore(int $studentId, int $semester, int $academicYearId): StudentConductScore
    {
        $academicYear = AcademicYear::findOrFail($academicYearId);

        // Calculate date range for semester
        $startDate = $semester === 1
            ? $academicYear->start_date
            : $academicYear->start_date->copy()->addMonths(5);

        $endDate = $semester === 1
            ? $academicYear->start_date->copy()->addMonths(5)
            : $academicYear->end_date;

        // Sum penalty points from confirmed disciplines
        $totalPenalty = Discipline::where('student_id', $studentId)
            ->where('status', 'confirmed')
            ->whereBetween('incident_date', [$startDate, $endDate])
            ->sum('penalty_points');

        // Update or create conduct score
        return $this->updateConductScore($studentId, $semester, $academicYearId, [
            'total_penalty_points' => $totalPenalty,
        ]);
    }
}
