<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Discipline;
use App\Models\StudentConductScore;
use App\Models\Student;
use App\Models\User;
use App\Repositories\Contracts\ConductScoreRepositoryInterface;
use App\Services\Interface\ConductScoreServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Log;

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
     * Tạo conduct score mới
     */
    public function createConductScore(array $data): StudentConductScore
    {
        return DB::transaction(function () use ($data) {
            // Check if already exists
            $existing = StudentConductScore::where('student_id', $data['student_id'])
                ->where('semester', $data['semester'])
                ->where('academic_year_id', $data['academic_year_id'])
                ->first();

            if ($existing) {
                throw new \Exception('Conduct score already exists for this student/semester/year', 409);
            }

            // Set defaults
            $data['total_penalty_points'] = $data['total_penalty_points'] ?? 0;
            $data['conduct_grade'] = $data['conduct_grade'] ?? 'good';

            return $this->conductScoreRepository->create($data);
        });
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
     * POST /api/conduct-scores/recalculate - Tính lại điểm hạnh kiểm và thống kê báo cáo kỷ luật
     * Permissions: admin, principal
     * Best Practices:
     * - Input validation with try-catch for invalid data (e.g., negative penalties).
     * - Use DB::transaction for atomicity in bulk updates.
     * - LEFT JOIN for comprehensive reporting (include students without scores).
     * - Explicit error handling and logging.
     * - Carbon for date handling.
     * - Avoid N+1 queries by eager loading where possible (though raw DB for reports).
     */
    public function recalculateConductScores(
        int $semester,
        int $academicYearId,
        ?int $classId = null,
        ?int $studentId = null
    ): array {
        // Step 0: Input Validation (Best Practice: Validate early)
        try {
            // Validate semester
            if (!in_array($semester, [1, 2])) {
                throw new \InvalidArgumentException('Semester must be 1 or 2.', 422);
            }

            // Validate academic year
            $academicYear = AcademicYear::findOrFail($academicYearId);

            // Validate class_id if provided
            if ($classId !== null) {
                SchoolClass::findOrFail($classId);
            }

            // Validate student_id if provided
            if ($studentId !== null) {
                $student = Student::findOrFail($studentId);
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw new \InvalidArgumentException('Invalid academic year, class, or student ID.', 422);
        } catch (\InvalidArgumentException $e) {
            throw $e;
        }

        // Calculate date range for semester (Best Practice: Use Carbon for immutable dates)
        $startDate = $semester === 1
            ? $academicYear->start_date->copy()
            : $academicYear->start_date->copy()->addMonths(5);

        $endDate = $semester === 1
            ? $academicYear->start_date->copy()->addMonths(5)->subDay()
            : $academicYear->end_date->copy();

        // Determine effective class ID for report
        $effectiveClassId = null;
        if (isset($student)) {
            $effectiveClassId = $student->class_id;
        } elseif ($classId !== null) {
            $effectiveClassId = $classId;
        }

        // Build query for students to update (Best Practice: Reuse query builder)
        $studentsQuery = Student::query();

        if ($studentId !== null) {
            $studentsQuery->where('id', $studentId);
        } elseif ($classId !== null) {
            $studentsQuery->where('class_id', $classId);
        }

        $students = $studentsQuery->get();
        $updated = 0;
        $errors = [];

        // Step 1: Recalculate conduct scores within transaction (Best Practice: Atomic bulk update)
        DB::transaction(function () use ($students, $semester, $academicYearId, $startDate, $endDate, &$updated, &$errors) {
            foreach ($students as $student) {
                try {
                    // Calculate penalty points from confirmed disciplines
                    $totalPenalty = Discipline::where('student_id', $student->id)
                        ->where('status', 'confirmed')
                        ->whereBetween('incident_date', [$startDate, $endDate])
                        ->sum('penalty_points');

                    // Validate calculated total_penalty (Best Practice: Explicit check for negatives, though sum() ensures >=0)
                    if ($totalPenalty < 0) {
                        throw new \InvalidArgumentException("Penalty points cannot be negative for student ID {$student->id}.", 422);
                    }

                    // Update conduct score (this internally uses createOrUpdate with transaction if needed)
                    $this->updateConductScore($student->id, $semester, $academicYearId, [
                        'total_penalty_points' => $totalPenalty,
                    ]);

                    $updated++;
                } catch (\InvalidArgumentException $e) {
                    // Re-throw validation errors to rollback transaction
                    throw $e;
                } catch (\Exception $e) {
                    // Log error (Best Practice: Log for debugging)
                    Log::error('Error recalculating conduct score for student', [
                        'student_id' => $student->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $errors[] = [
                        'student_id' => $student->id,
                        'error' => $e->getMessage()
                    ];
                    // Continue processing other students (partial success in bulk)
                }
            }
        });

        // Step 2: Generate discipline report using updated conduct scores
        // Best Practice: Use raw DB query for performance in reporting; LEFT JOIN for completeness
        $conductQuery = DB::table('classes as c')
            ->join('students as s', 's.class_id', '=', 'c.id')
            ->leftJoin('student_conduct_scores as cs', function ($join) use ($semester, $academicYearId) {
                $join->on('cs.student_id', '=', 's.id')
                    ->where('cs.semester', '=', $semester)
                    ->where('cs.academic_year_id', '=', $academicYearId);
            })
            ->select(
                'c.id as class_id',
                'c.name as class_name',
                DB::raw('COUNT(DISTINCT s.id) as num_students'),
                DB::raw('ROUND(AVG(COALESCE(cs.total_penalty_points, 0)), 2) as avg_penalty_points'),
                DB::raw('SUM(CASE WHEN COALESCE(cs.total_penalty_points, 0) > 0 THEN 1 ELSE 0 END) as num_violating_students')
            )
            ->when($effectiveClassId !== null, function ($q) use ($effectiveClassId) {
                return $q->where('c.id', $effectiveClassId);
            })
            ->groupBy('c.id', 'c.name')
            ->get();

        // Step 3: Get violations by type (Best Practice: Separate query for types; assume discipline_types table exists)
        $typeQuery = DB::table('disciplines as d')
            ->join('students as s', 'd.student_id', '=', 's.id')
            ->join('classes as c', 's.class_id', '=', 'c.id')
            ->join('discipline_types as dt', 'd.discipline_type_id', '=', 'dt.id')
            ->where('d.status', '=', 'confirmed')
            ->whereBetween('d.incident_date', [$startDate, $endDate])
            ->select(
                'c.id as class_id',
                'c.name as class_name',
                'dt.name as violation_type',
                'dt.severity_level',
                DB::raw('COUNT(DISTINCT d.student_id) as count')
            )
            ->when($effectiveClassId !== null, function ($q) use ($effectiveClassId) {
                return $q->where('c.id', $effectiveClassId);
            })
            ->groupBy('c.id', 'c.name', 'dt.id', 'dt.name', 'dt.severity_level')
            ->orderBy('c.id')
            ->orderBy('dt.name')
            ->get();

        // Map violations by class (Best Practice: Use associative array for O(1) lookup)
        $typeMap = [];
        foreach ($typeQuery as $row) {
            $classId = (int) $row->class_id;
            if (!isset($typeMap[$classId])) {
                $typeMap[$classId] = [];
            }
            $typeMap[$classId][$row->violation_type] = [
                'count' => (int) $row->count,
                'severity_level' => $row->severity_level
            ];
        }

        // Build final report (Best Practice: Type casting for JSON serialization)
        $report = [];
        foreach ($conductQuery as $row) {
            $classId = (int) $row->class_id;
            $report[] = [
                'class_id' => $classId,
                'class_name' => $row->class_name,
                'num_students' => (int) $row->num_students,
            ];
        }

        // Return structured response (Best Practice: Include metadata for traceability)
        return [
            'recalculation' => [
                'total_students_processed' => $students->count(),
            ],
            'period' => [
                'semester' => $semester,
                'academic_year_id' => $academicYearId,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]
        ];
    }
}
