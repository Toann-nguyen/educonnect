<?php

namespace App\Services;

use App\Services\Interface\DisciplineServiceInterface;
use App\Models\Discipline;
use App\Models\DisciplineAppeal;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DisciplineService implements DisciplineServiceInterface
{
    public function getAllDisciplines(array $filters): LengthAwarePaginator
    {
        $query = Discipline::with(['student.user.profile', 'disciplineType', 'reporter.profile', 'reviewer.profile'])
            ->orderBy('incident_date', 'desc');

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by class
        if (isset($filters['class_id'])) {
            $query->byClass($filters['class_id']);
        }

        // Filter by student
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        // Filter by severity level
        if (isset($filters['severity_level'])) {
            $query->whereHas('disciplineType', function ($q) use ($filters) {
                $q->where('severity_level', $filters['severity_level']);
            });
        }

        // Filter by date range
        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        // Filter by reporter
        if (isset($filters['reporter_id'])) {
            $query->where('reporter_user_id', $filters['reporter_id']);
        }

        // Search by student name or description
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('student.user.profile', function ($p) use ($search) {
                    $p->where('full_name', 'like', "%{$search}%");
                })->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getMyDisciplines(User $user): LengthAwarePaginator
    {
        $query = Discipline::with(['disciplineType', 'reporter.profile', 'reviewer.profile'])
            ->where('status', 'confirmed') // Chỉ xem những bản đã confirmed
            ->orderBy('incident_date', 'desc');

        if ($user->hasRole('student')) {
            // Học sinh xem của mình
            $query->whereHas('student', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($user->hasRole('parent')) {
            // Phụ huynh xem của con
            $query->whereHas('student.guardians', function ($q) use ($user) {
                $q->where('guardian_user_id', $user->id);
            });
        }

        return $query->paginate(15);
    }

    public function getDisciplinesByClass(int $classId, array $filters): LengthAwarePaginator
    {
        $query = Discipline::with(['student.user.profile', 'disciplineType', 'reporter.profile'])
            ->byClass($classId)
            ->orderBy('incident_date', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getDisciplinesByStudent(int $studentId, array $filters): LengthAwarePaginator
    {
        $query = Discipline::with(['disciplineType', 'reporter.profile', 'reviewer.profile', 'actions'])
            ->where('student_id', $studentId)
            ->orderBy('incident_date', 'desc');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['semester']) && isset($filters['academic_year_id'])) {
            // Calculate semester date range
            $academicYear = \App\Models\AcademicYear::find($filters['academic_year_id']);
            if ($academicYear) {
                $startDate = $filters['semester'] == 1
                    ? $academicYear->start_date
                    : $academicYear->start_date->copy()->addMonths(5);

                $endDate = $filters['semester'] == 1
                    ? $academicYear->start_date->copy()->addMonths(5)
                    : $academicYear->end_date;

                $query->dateRange($startDate, $endDate);
            }
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function createDiscipline(array $data): Discipline
    {
        return DB::transaction(function () use ($data) {
            $discipline = Discipline::create($data);

            // Nếu có attachments, xử lý upload file ở đây

            return $discipline->load(['student.user.profile', 'disciplineType', 'reporter.profile']);
        });
    }

    public function updateDiscipline(Discipline $discipline, array $data): Discipline
    {
        return DB::transaction(function () use ($discipline, $data) {
            $discipline->update($data);
            return $discipline->fresh(['student.user.profile', 'disciplineType', 'reporter.profile']);
        });
    }

    public function deleteDiscipline(Discipline $discipline): bool
    {
        return $discipline->delete();
    }

    public function approveDiscipline(Discipline $discipline, User $reviewer, ?string $note = null): Discipline
    {
        return DB::transaction(function () use ($discipline, $reviewer, $note) {
            $discipline->update([
                'status' => 'confirmed',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            // TODO: Trigger notification to parent and student

            return $discipline->fresh();
        });
    }

    public function rejectDiscipline(Discipline $discipline, User $reviewer, string $reason): Discipline
    {
        return DB::transaction(function () use ($discipline, $reviewer, $reason) {
            $discipline->update([
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ]);

            // TODO: Trigger notification to reporter

            return $discipline->fresh();
        });
    }

    public function createAppeal(Discipline $discipline, User $appellant, string $reason, ?array $evidence = null): bool
    {
        return DB::transaction(function () use ($discipline, $appellant, $reason, $evidence) {
            $appellantType = $appellant->hasRole('student') ? 'student' : 'parent';

            DisciplineAppeal::create([
                'discipline_id' => $discipline->id,
                'appellant_user_id' => $appellant->id,
                'appellant_type' => $appellantType,
                'appeal_reason' => $reason,
                'evidence' => $evidence,
                'status' => 'pending',
            ]);

            $discipline->update(['status' => 'appealed']);

            // TODO: Trigger notification to reviewer

            return true;
        });
    }

    public function getStatistics(array $filters): array
    {
        $query = Discipline::query();

        // Apply filters
        if (isset($filters['class_id'])) {
            $query->byClass($filters['class_id']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->byAcademicYear($filters['academic_year_id']);
        }

        $total = $query->count();
        $byStatus = $query->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $bySeverity = $query->join('discipline_types', 'disciplines.discipline_type_id', '=', 'discipline_types.id')
            ->select('discipline_types.severity_level', DB::raw('count(*) as count'))
            ->groupBy('discipline_types.severity_level')
            ->pluck('count', 'severity_level')
            ->toArray();

        $topViolations = Discipline::join('discipline_types', 'disciplines.discipline_type_id', '=', 'discipline_types.id')
            ->select('discipline_types.name', DB::raw('count(*) as count'))
            ->groupBy('discipline_types.id', 'discipline_types.name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_severity' => $bySeverity,
            'top_violations' => $topViolations,
            'pending_count' => $byStatus['pending'] ?? 0,
            'confirmed_count' => $byStatus['confirmed'] ?? 0,
            'appealed_count' => $byStatus['appealed'] ?? 0,
        ];
    }

    public function canAccess(User $user, Discipline $discipline): bool
    {
        // Admin/Principal có thể xem tất cả
        if ($user->hasAnyRole(['admin', 'principal'])) {
            return true;
        }

        // Teacher có thể xem nếu là reporter hoặc GVCN của lớp
        if ($user->hasRole('teacher')) {
            if ($discipline->reporter_user_id === $user->id) {
                return true;
            }

            $isHomeroom = $discipline->student->schoolClass
                && $discipline->student->schoolClass->homeroom_teacher_id === $user->id;

            return $isHomeroom;
        }

        // Student chỉ xem của mình và phải confirmed
        if ($user->hasRole('student')) {
            return $discipline->student->user_id === $user->id
                && $discipline->status === 'confirmed';
        }

        // Parent xem của con và phải confirmed
        if ($user->hasRole('parent')) {
            $isParent = $discipline->student->guardians()
                ->where('guardian_user_id', $user->id)
                ->exists();

            return $isParent && $discipline->status === 'confirmed';
        }

        return false;
    }

    public function canModify(User $user, Discipline $discipline): bool
    {
        // Admin/Principal có thể sửa tất cả
        if ($user->hasAnyRole(['admin', 'principal'])) {
            return true;
        }

        // Teacher chỉ sửa được bản ghi của mình và chưa được duyệt
        if ($user->hasRole('teacher')) {
            return $discipline->reporter_user_id === $user->id
                && $discipline->status === 'pending';
        }

        return false;
    }
}
