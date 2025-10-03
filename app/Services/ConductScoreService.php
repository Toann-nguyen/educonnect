<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Discipline;
use App\Models\StudentConductScore;
use App\Models\User;
use App\Repositories\Contracts\ConductScoreRepositoryInterface;
use App\Services\Interface\ConductScoreServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConductScoreService implements ConductScoreServiceInterface
{
    protected $conductScoreRepository;

    public function __construct(ConductScoreRepositoryInterface $conductScoreRepository)
    {
        $this->conductScoreRepository = $conductScoreRepository;
    }

    public function getMyConductScores(User $user, array $filters): LengthAwarePaginator
    {
        if ($user->hasRole('student')) {
            return $this->conductScoreRepository->getByStudentUserId($user->id, $filters);
        } elseif ($user->hasRole('parent')) {
            return $this->conductScoreRepository->getByParentUserId($user->id, $filters);
        }

        // Fallback - return empty result
        return collect([])->paginate(15);
    }

    public function getConductScoresByClass(int $classId, array $filters): LengthAwarePaginator
    {
        return $this->conductScoreRepository->getByClassId($classId, $filters);
    }

    public function getStudentConductScore(int $studentId, int $semester, int $academicYearId): ?StudentConductScore
    {
        return $this->conductScoreRepository->findByStudentSemester($studentId, $semester, $academicYearId);
    }

    public function updateConductScore(int $studentId, int $semester, int $academicYearId, array $data): StudentConductScore
    {
        return DB::transaction(function () use ($studentId, $semester, $academicYearId, $data) {
            $data['student_id'] = $studentId;
            $data['semester'] = $semester;
            $data['academic_year_id'] = $academicYearId;

            return $this->conductScoreRepository->createOrUpdate($data);
        });
    }

    public function approveConductScore(StudentConductScore $conductScore, User $approver): StudentConductScore
    {
        return $this->conductScoreRepository->update($conductScore->id, [
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);
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
