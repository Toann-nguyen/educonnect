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
use Illuminate\Database\Eloquent\Collection;

class ConductScoreService implements ConductScoreServiceInterface
{
    protected $conductScoreRepository;

    public function __construct(ConductScoreRepositoryInterface $conductScoreRepository)
    {
        $this->conductScoreRepository = $conductScoreRepository;
    }

    /**
     * Lấy conduct scores của user hiện tại (Student/Parent)
     */
    public function getMyConductScores(
        User $user,
        ?int $semester = null,
        ?int $academicYearId = null
    ): Collection {
        $filters = [];
        if ($semester) {
            $filters['semester'] = $semester;
        }
        if ($academicYearId) {
            $filters['academic_year_id'] = $academicYearId;
        }

        if ($user->hasRole('student')) {
            // Lấy conduct scores của chính mình
            return $this->conductScoreRepository->findAllByStudent(
                $user->student->id,
                $semester,
                $academicYearId
            );
        } elseif ($user->hasRole('parent')) {
            // Lấy conduct scores của tất cả các con
            $childrenIds = $user->guardianStudents()->pluck('students.id')->toArray();

            if (empty($childrenIds)) {
                return collect([]);
            }

            return StudentConductScore::whereIn('student_id', $childrenIds)
                ->when($semester, fn($q) => $q->where('semester', $semester))
                ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
                ->with(['student.user.profile', 'academicYear', 'approver.profile'])
                ->orderBy('academic_year_id', 'desc')
                ->orderBy('semester', 'desc')
                ->get();
        }

        return collect([]);
    }

    /**
     * Lấy conduct scores của một lớp
     */
    public function getClassConductScores(
        int $classId,
        User $user,
        ?int $semester = null,
        ?int $academicYearId = null
    ): Collection {
        // Check authorization
        if (!$user->hasRole(['admin', 'principal'])) {
            // Teacher chỉ xem được lớp chủ nhiệm của mình
            if ($user->hasRole('teacher')) {
                $isHomeroom = $user->homeroomClasses()->where('id', $classId)->exists();
                if (!$isHomeroom) {
                    throw new \Exception('Unauthorized to view this class conduct scores');
                }
            } else {
                throw new \Exception('Unauthorized to view class conduct scores');
            }
        }

        $query = StudentConductScore::whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        })->with(['student.user.profile', 'student.schoolClass', 'academicYear', 'approver.profile']);

        if ($semester) {
            $query->where('semester', $semester);
        }

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->orderByRaw("FIELD(conduct_grade, 'excellent', 'good', 'average', 'weak')")
            ->orderBy('total_penalty_points')
            ->get();
    }

    /**
     * Lấy 1 conduct score cụ thể của học sinh (theo semester và năm học)
     */
    public function getStudentConductScore(
        int $studentId,
        int $semester,
        int $academicYearId
    ): ?StudentConductScore {
        return $this->conductScoreRepository->findByStudentSemester(
            $studentId,
            $semester,
            $academicYearId
        );
    }

    /**
     * Lấy TẤT CẢ conduct scores của một học sinh (có thể filter)
     */
    public function getAllStudentConductScores(
        int $studentId,
        ?int $semester = null,
        ?int $academicYearId = null
    ): Collection {
        return $this->conductScoreRepository->findAllByStudent(
            $studentId,
            $semester,
            $academicYearId
        );
    }

    /**
     * Cập nhật conduct score
     */
    public function updateConductScore(
        int $studentId,
        int $semester,
        int $academicYearId,
        array $data
    ): StudentConductScore {
        return DB::transaction(function () use ($studentId, $semester, $academicYearId, $data) {
            $data['student_id'] = $studentId;
            $data['semester'] = $semester;
            $data['academic_year_id'] = $academicYearId;

            return $this->conductScoreRepository->createOrUpdate($data);
        });
    }

    /**
     * Phê duyệt conduct score
     */
    public function approveConductScore($conductScoreId)
    {
        $conductScore = StudentConductScore::find($conductScoreId);

        if (!$conductScore) {
            return null;
        }

        // Update
        $conductScore->update([
            'approved_by_user_id' => auth()->id(),
            'approved_at' => now(),
            // các field khác nếu cần
        ]);

        // ✅ THÊM: Return data sau update
        return $conductScore->fresh();
    }

    /**
     * Tính lại conduct score từ discipline records
     */
    public function recalculateConductScore(
        int $studentId,
        int $semester,
        int $academicYearId
    ): StudentConductScore {
        $academicYear = AcademicYear::findOrFail($academicYearId);

        // Calculate date range for semester
        $startDate = $semester === 1
            ? $academicYear->start_date
            : $academicYear->start_date->copy()->addMonths(5);

        $endDate = $semester === 1
            ? $academicYear->start_date->copy()->addMonths(5)->subDay()
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

    /**
     * Tính lại conduct scores cho nhiều học sinh (bulk)
     */
    public function recalculateBulk(
        int $semester,
        int $academicYearId,
        ?int $classId = null,
        ?int $studentId = null
    ): array {
        $academicYear = AcademicYear::findOrFail($academicYearId);

        // Calculate date range
        $startDate = $semester === 1
            ? $academicYear->start_date
            : $academicYear->start_date->copy()->addMonths(5);

        $endDate = $semester === 1
            ? $academicYear->start_date->copy()->addMonths(5)->subDay()
            : $academicYear->end_date;

        // Build query for students
        $studentsQuery = \App\Models\Student::query();

        if ($studentId) {
            $studentsQuery->where('id', $studentId);
        } elseif ($classId) {
            $studentsQuery->where('class_id', $classId);
        }

        $students = $studentsQuery->get();
        $updated = 0;
        $errors = [];

        foreach ($students as $student) {
            try {
                // Calculate penalty points
                $totalPenalty = Discipline::where('student_id', $student->id)
                    ->where('status', 'confirmed')
                    ->whereBetween('incident_date', [$startDate, $endDate])
                    ->sum('penalty_points');

                // Update conduct score
                $this->updateConductScore($student->id, $semester, $academicYearId, [
                    'total_penalty_points' => $totalPenalty,
                ]);

                $updated++;
            } catch (\Exception $e) {
                $errors[] = [
                    'student_id' => $student->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'total_students' => $students->count(),
            'updated' => $updated,
            'errors' => $errors
        ];
    }
}