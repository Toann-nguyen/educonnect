<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use \App\Models\Grade;
use Exception;
use App\Repositories\Contracts\GradeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class GradeRepository implements GradeRepositoryInterface
{
    public function getByStudentId(int $studentId)
    {
        return Grade::where('student_id', $studentId)
            ->with('subject:id,name') // Lấy kèm tên môn học
            ->orderBy('semester')
            ->orderBy('subject_id')
            ->get();
    }

    public function getAll(array $filters, User $user): LengthAwarePaginator
    {

        try {
            $query = Grade::with(['student.user.profile', 'subject', 'teacher.profile']);

            if (isset($filters['student_id'])) $query->where('student_id', $filters['student_id']);
            if (isset($filters['subject_id'])) $query->where('subject_id', $filters['subject_id']);
            if (isset($filters['semester'])) $query->where('semester', $filters['semester']);
            if (isset($filters['type'])) $query->where('type', $filters['type']);

            // 3. Áp dụng logic phân quyền (authorization)
            // Sử dụng if/elseif để làm cho logic rõ ràng hơn
            if ($user->hasRole(['admin', 'principal'])) {
                // Nếu là Admin hoặc Principal, không cần thêm điều kiện nào cả.
                // Họ có thể xem toàn bộ dữ liệu (đã được lọc ở trên).
            } elseif ($user->hasRole('teacher')) {
                // Nếu là Teacher, chỉ cho phép xem các điểm số do chính họ tạo.
                $query->where('teacher_id', $user->id);
            } else {
                // Đối với các vai trò khác (ví dụ: student, parent tự gọi nhầm endpoint này),
                // không cho phép xem bất kỳ điểm nào.
                // Trả về một kết quả rỗng bằng cách thêm một điều kiện luôn sai.
                $query->whereRaw('1 = 0');
            }

            // 4. Sắp xếp và thực hiện phân trang
            return $query->orderBy('id', 'desc')->paginate($filters['per_page'] ?? 15);
        } catch (Exception $e) {
            Log::error('Failed to get all grades.', ['error' => $e->getMessage(), 'filters' => $filters]);
            throw $e;
        }
    }

    public function create(array $data): Grade
    {
        try {
            $grade = Grade::create($data);
            Log::info('Grade created successfully.', ['grade_id' => $grade->id, 'data' => $data]);
            return $grade->load(['student.user.profile', 'subject', 'teacher.profile']);
        } catch (Exception $e) {
            Log::error('Failed to create grade.', ['error' => $e->getMessage(), 'data' => $data]);
            throw $e; // Ném lỗi ra để Service có thể xử lý transaction
        }
    }

    public function update(int $gradeId, array $data): Grade
    {
        try {
            $grade = Grade::findOrFail($gradeId);
            $grade->update($data);
            Log::info('Grade updated successfully.', ['grade_id' => $gradeId, 'data' => $data]);
            return $grade->load(['student.user.profile', 'subject', 'teacher.profile']);
        } catch (Exception $e) {
            Log::error('Failed to update grade.', ['error' => $e->getMessage(), 'grade_id' => $gradeId, 'data' => $data]);
            throw $e;
        }
    }

    public function delete(int $gradeId): bool
    {
        try {
            $result = Grade::destroy($gradeId) > 0;
            Log::info('Grade deleted successfully.', ['grade_id' => $gradeId]);
            return $result;
        } catch (Exception $e) {
            Log::error('Failed to delete grade.', ['error' => $e->getMessage(), 'grade_id' => $gradeId]);
            throw $e;
        }
    }

    public function getByClass(int $classId, array $filters, User $user): Collection
    {
        try {
            $query = Grade::with(['student.user.profile', 'subject', 'teacher.profile'])
                ->whereHas('student', fn($q) => $q->where('class_id', $classId));

            if ($user->hasRole('teacher') && !$user->hasRole('admin')) $query->where('teacher_id', $user->id);
            if (isset($filters['subject_id'])) $query->where('subject_id', $filters['subject_id']);
            if (isset($filters['semester'])) $query->where('semester', $filters['semester']);

            return $query->orderBy('student_id')->orderBy('subject_id')->get();
        } catch (Exception $e) {
            Log::error('Failed to get grades by class.', ['error' => $e->getMessage(), 'class_id' => $classId]);
            throw $e;
        }
    }

    public function getStatsForStudent(int $studentId): Collection
    {
        try {
            return Grade::where('student_id', $studentId)->with('subject')->get();
        } catch (Exception $e) {
            Log::error('Failed to get stats for student.', ['error' => $e->getMessage(), 'student_id' => $studentId]);
            throw $e;
        }
    }
}
