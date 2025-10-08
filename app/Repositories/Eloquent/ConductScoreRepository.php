<?php

namespace App\Repositories\Eloquent;

use App\Models\StudentConductScore;
use App\Repositories\Contracts\ConductScoreRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class ConductScoreRepository implements ConductScoreRepositoryInterface
{
    protected $model;

    public function __construct(StudentConductScore $model)
    {
        $this->model = $model;
    }

    public function getByStudentUserId(int $userId, array $filters): LengthAwarePaginator
    {
        $query = $this->model->whereHas('student', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->with(['student.user.profile', 'student.schoolClass', 'academicYear', 'approver.profile']);

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

    public function getByParentUserId(int $parentUserId, array $filters): LengthAwarePaginator
    {
        $query = $this->model->whereHas('student.guardians', function ($q) use ($parentUserId) {
            $q->where('guardian_user_id', $parentUserId);
        })
            ->with([
                'student.user.profile',
                'student.schoolClass',
                'academicYear',
                'approver.profile'
            ]);

        if (isset($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        // Có thể filter theo student_id nếu phụ huynh có nhiều con
        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        return $query->orderBy('academic_year_id', 'desc')
            ->orderBy('semester', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getByClassId(int $classId, array $filters): LengthAwarePaginator
    {
        $query = $this->model->whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        })
            ->with(['student.user.profile']);

        if (isset($filters['semester'])) {
            $query->where('semester', $filters['semester']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['conduct_grade'])) {
            $query->where('conduct_grade', $filters['conduct_grade']);
        }

        // Sort by conduct grade and penalty points
        return $query->orderByRaw("FIELD(conduct_grade, 'excellent', 'good', 'average', 'weak')")
            ->orderBy('total_penalty_points')
            ->paginate($filters['per_page'] ?? 50);
    }

    public function findByStudentSemester(int $studentId, int $semester, int $academicYearId): ?StudentConductScore
    {
        return $this->model->where('student_id', $studentId)
            ->where('semester', $semester)
            ->where('academic_year_id', $academicYearId)
            ->with(['student.user.profile', 'academicYear', 'approver.profile'])
            ->first();
    }
    /**
     * Lấy tất cả conduct scores của một học sinh (có filter tùy chọn)
     */
    public function findAllByStudent(
        int $studentId,
        ?int $semester = null,
        ?int $academicYearId = null
    ) {
        $query = $this->model->where('student_id', $studentId)
            ->with(['student.user.profile', 'academicYear', 'approver.profile']);

        if ($semester) {
            $query->where('semester', $semester);
        }

        if ($academicYearId) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query->orderBy('academic_year_id', 'desc')
            ->orderBy('semester', 'desc')
            ->get();
    }


    public function createOrUpdate(array $data): StudentConductScore
    {
        try {
            $conductScore = $this->model->updateOrCreate(
                [
                    'student_id' => $data['student_id'],
                    'semester' => $data['semester'],
                    'academic_year_id' => $data['academic_year_id'],
                ],
                $data
            );

            Log::info('ConductScore created or updated successfully.', [
                'id' => $conductScore->id,
                'student_id' => $data['student_id']
            ]);

            return $conductScore->fresh(['student.user.profile', 'academicYear', 'approver.profile']);
        } catch (\Exception $e) {
            Log::error('Failed to create or update ConductScore.', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function update(int $id, array $data): StudentConductScore
    {
        try {
            $conductScore = $this->model->findOrFail($id);
            $conductScore->update($data);

            Log::info('ConductScore updated successfully.', ['id' => $id]);

            return $conductScore->fresh(['student.user.profile', 'academicYear', 'approver.profile']);
        } catch (\Exception $e) {
            Log::error('Failed to update ConductScore.', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        try {
            $result = $this->model->destroy($id) > 0;
            Log::info('ConductScore deleted successfully.', ['id' => $id]);
            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to delete ConductScore.', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw $e;
        }
    }

    public function getClassConductScores(int $classId, int $semester, int $academicYearId): Collection
    {
        return $this->model->whereHas('student', function ($q) use ($classId) {
            $q->where('class_id', $classId);
        })
            ->where('semester', $semester)
            ->where('academic_year_id', $academicYearId)
            ->with(['student.user.profile'])
            ->orderByRaw("FIELD(conduct_grade, 'excellent', 'good', 'average', 'weak')")
            ->orderBy('total_penalty_points')
            ->get();
    }
}
