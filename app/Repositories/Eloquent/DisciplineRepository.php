<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\DisciplineRepositoryInterface;
use App\Models\Discipline;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

class DisciplineRepository implements DisciplineRepositoryInterface
{
    
    protected $model;

    public function __construct(Discipline $model)
    {
        $this->model = $model;
    }

    public function getAll(array $filters): LengthAwarePaginator
    {
        $query = $this->model->with([
            'student.user.profile',
            'student.schoolClass',
            'disciplineType',
            'reporter.profile',
            'reviewer.profile'
        ]);

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

        // Filter by discipline type
        if (isset($filters['discipline_type_id'])) {
            $query->where('discipline_type_id', $filters['discipline_type_id']);
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

        // Filter by academic year
        if (isset($filters['academic_year_id'])) {
            $query->byAcademicYear($filters['academic_year_id']);
        }

        // Search by student name or description
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('student.user.profile', function ($p) use ($search) {
                    $p->where('full_name', 'like', "%{$search}%");
                })->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('incident_location', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('incident_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findById(int $id): ?Discipline
    {
        return $this->model->with([
            'student.user.profile',
            'student.schoolClass',
            'disciplineType',
            'reporter.profile',
            'reviewer.profile',
            'actions.executor.profile',
            'appeals.appellant.profile'
        ])->find($id);
    }

    public function getByStudentId(int $studentId, array $filters): LengthAwarePaginator
    {
        $query = $this->model->where('student_id', $studentId)
            ->with([
                'disciplineType',
                'reporter.profile',
                'reviewer.profile',
                'actions'
            ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['semester']) && isset($filters['academic_year_id'])) {
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

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        return $query->orderBy('incident_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getByClassId(int $classId, array $filters): LengthAwarePaginator
    {
        $query = $this->model->byClass($classId)
            ->with([
                'student.user.profile',
                'disciplineType',
                'reporter.profile',
                'reviewer.profile'
            ]);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->dateRange($filters['start_date'], $filters['end_date']);
        }

        if (isset($filters['severity_level'])) {
            $query->whereHas('disciplineType', function ($q) use ($filters) {
                $q->where('severity_level', $filters['severity_level']);
            });
        }

        return $query->orderBy('incident_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getByStudentUserId(int $userId): LengthAwarePaginator
    {
        return $this->model->whereHas('student', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->where('status', 'confirmed') // Học sinh chỉ xem được confirmed
            ->with([
                'disciplineType',
                'reporter.profile',
                'reviewer.profile',
                'actions',
                'appeals'
            ])
            ->orderBy('incident_date', 'desc')
            ->paginate(15);
    }

    public function getByParentUserId(int $parentUserId): LengthAwarePaginator
    {
        return $this->model->whereHas('student.guardians', function ($q) use ($parentUserId) {
            $q->where('guardian_user_id', $parentUserId);
        })
            ->where('status', 'confirmed') // Phụ huynh chỉ xem được confirmed
            ->with([
                'student.user.profile',
                'student.schoolClass',
                'disciplineType',
                'reporter.profile',
                'reviewer.profile',
                'actions'
            ])
            ->orderBy('incident_date', 'desc')
            ->paginate(15);
    }

    public function create(array $data): Discipline
    {
        try {
            $discipline = $this->model->create($data);
            Log::info('Discipline created successfully.', ['discipline_id' => $discipline->id]);
            return $discipline->load([
                'student.user.profile',
                'disciplineType',
                'reporter.profile'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create discipline.', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function update(int $id, array $data): Discipline
    {
        try {
            $discipline = $this->model->findOrFail($id);
            $discipline->update($data);
            Log::info('Discipline updated successfully.', ['discipline_id' => $id]);
            return $discipline->fresh([
                'student.user.profile',
                'disciplineType',
                'reporter.profile',
                'reviewer.profile'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update discipline.', [
                'error' => $e->getMessage(),
                'discipline_id' => $id,
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $result = $this->model->destroy($id) > 0;
            Log::info('Discipline deleted successfully.', ['discipline_id' => $id]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to delete discipline.', [
                'error' => $e->getMessage(),
                'discipline_id' => $id
            ]);
            throw $e;
        }
    }

    public function getStatistics(array $filters = []): array
    {
        $query = $this->model->query();

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

        $bySeverity = DB::table('disciplines')
            ->join('discipline_types', 'disciplines.discipline_type_id', '=', 'discipline_types.id')
            ->select('discipline_types.severity_level', DB::raw('count(*) as count'))
            ->when(isset($filters['class_id']), function ($q) use ($filters) {
                $q->whereExists(function ($subQuery) use ($filters) {
                    $subQuery->select(DB::raw(1))
                        ->from('students')
                        ->whereColumn('students.id', 'disciplines.student_id')
                        ->where('students.class_id', $filters['class_id']);
                });
            })
            ->when(isset($filters['start_date']) && isset($filters['end_date']), function ($q) use ($filters) {
                $q->whereBetween('disciplines.incident_date', [$filters['start_date'], $filters['end_date']]);
            })
            ->groupBy('discipline_types.severity_level')
            ->pluck('count', 'severity_level')
            ->toArray();

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'by_severity' => $bySeverity,
            'pending_count' => $byStatus['pending'] ?? 0,
            'confirmed_count' => $byStatus['confirmed'] ?? 0,
            'rejected_count' => $byStatus['rejected'] ?? 0,
            'appealed_count' => $byStatus['appealed'] ?? 0,
        ];
    }

    public function getTopViolations(int $limit = 10, array $filters = []): Collection
    {
        $query = DB::table('disciplines')
            ->join('discipline_types', 'disciplines.discipline_type_id', '=', 'discipline_types.id')
            ->select(
                'discipline_types.id',
                'discipline_types.name',
                'discipline_types.code',
                'discipline_types.severity_level',
                DB::raw('count(*) as count')
            );

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('disciplines.incident_date', [$filters['start_date'], $filters['end_date']]);
        }

        if (isset($filters['class_id'])) {
            $query->whereExists(function ($subQuery) use ($filters) {
                $subQuery->select(DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.id', 'disciplines.student_id')
                    ->where('students.class_id', $filters['class_id']);
            });
        }

        return $query->groupBy('discipline_types.id', 'discipline_types.name', 'discipline_types.code', 'discipline_types.severity_level')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    public function findTrashedById(int $id): ?Discipline
    {
        return $this->model->onlyTrashed()->find($id);
    }

    public function restore(int $id): bool
    {
        $discipline = $this->findTrashedById($id);
        if ($discipline) {
            return $discipline->restore();
        }
        return false;
    }
}
