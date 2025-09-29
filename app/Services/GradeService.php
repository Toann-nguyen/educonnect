<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use App\Repositories\Contracts\GradeRepositoryInterface;
use App\Services\Interface\GradeServiceInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

use App\Models\Grade;
use Exception;

class GradeService implements GradeServiceInterface
{
    protected $gradeRepository;
    public function __construct(GradeRepositoryInterface $gradeRepository)
    {
        $this->gradeRepository = $gradeRepository;
    }
    public function getPersonalGrades(User $user)
    {
        if ($user->hasRole('student') && $user->student) {
            // Nếu là học sinh, chỉ lấy điểm của chính mình
            $grades = $this->gradeRepository->getByStudentId($user->student->id);

            return [
                'student_id' => $user->student->id,
                'student_name' => $user->profile->full_name,
                'grades' => $grades,
            ];
        }

        if ($user->hasRole('parent')) {
            // Nếu là phụ huynh, lấy điểm của tất cả các con
            $childrenGrades = [];
            // guardianStudents là mối quan hệ đã được định nghĩa trong Model User
            $children = $user->guardianStudents()->with('user.profile')->get();

            foreach ($children as $child) {
                $grades = $this->gradeRepository->getByStudentId($child->id);
                $childrenGrades[] = [
                    'student_id' => $child->id,
                    'student_name' => $child->user->profile->full_name,
                    'grades' => $grades,
                ];
            }
            return $childrenGrades;
        }
        return [];
    }
    public function getAllGrades(array $filters, User $user): LengthAwarePaginator
    {
        return $this->gradeRepository->getAll($filters, $user);
    }
    public function createGrade(array $data, User $creator): Grade
    {
        // Bắt đầu một transaction
        DB::beginTransaction();
        try {
            $data['teacher_id'] = $creator->id;

            $grade = $this->gradeRepository->create($data);

            // Ví dụ: Sau khi tạo điểm, có thể bạn muốn thực hiện một hành động khác,
            // ví dụ như cập nhật điểm trung bình của học sinh.
            // $this->studentService->recalculateAverageScore($grade->student_id);

            // Nếu tất cả thành công, commit transaction
            DB::commit();
            Log::info('Transaction for creating grade committed.', ['grade_id' => $grade->id]);

            return $grade;
        } catch (Exception $e) {
            // Nếu có bất kỳ lỗi nào, rollback tất cả các thay đổi trong CSDL
            DB::rollBack();
            Log::error('Transaction for creating grade rolled back.', ['error' => $e->getMessage()]);

            // Ném lại lỗi để Controller có thể bắt và trả về response 500
            throw $e;
        }
    }

    public function updateGrade(Grade $grade, array $data, User $updater): Grade
    {
        // Logic phân quyền
        if (!$updater->hasRole('admin') && $grade->teacher_id !== $updater->id) {
            throw new AuthorizationException('You can only update your own grades.');
        }

        DB::beginTransaction();
        try {
            $updatedGrade = $this->gradeRepository->update($grade->id, $data);
            DB::commit();
            Log::info('Transaction for updating grade committed.', ['grade_id' => $grade->id]);
            return $updatedGrade;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Transaction for updating grade rolled back.', ['grade_id' => $grade->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function deleteGrade(Grade $grade, User $deleter): bool
    {
        // Logic phân quyền
        if (!$deleter->hasRole('admin') && $grade->teacher_id !== $deleter->id) {
            throw new AuthorizationException('You can only delete your own grades.');
        }

        DB::beginTransaction();
        try {
            $result = $this->gradeRepository->delete($grade->id);
            DB::commit();
            Log::info('Transaction for deleting grade committed.', ['grade_id' => $grade->id]);
            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Transaction for deleting grade rolled back.', ['grade_id' => $grade->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function checkViewPermission(Grade $grade, User $user): void
    {
        $isAllowed = match (true) {
            $user->hasRole(['admin', 'teacher']) => true,
            $user->hasRole('student') && $grade->student_id === $user->student?->id => true,
            $user->hasRole('parent') && $user->guardianStudents()->where('students.id', $grade->student_id)->exists() => true,
            default => false,
        };
        if (!$isAllowed) {
            throw new AuthorizationException('You are not authorized to view this grade.');
        }
    }

    public function getGradesByClass(int $classId, array $filters, User $user): Collection
    {
        return $this->gradeRepository->getByClass($classId, $filters, $user);
    }

    public function getStudentGradeStats(int $studentId, User $user): array
    {
        // Logic phân quyền
        $isAllowed = match (true) {
            $user->hasRole(['admin', 'teacher', 'principal']) => true,
            $user->hasRole('student') && $user->student?->id == $studentId => true,
            $user->hasRole('parent') && $user->guardianStudents()->where('students.id', $studentId)->exists() => true,
            default => false
        };
        if (!$isAllowed) {
            throw new AuthorizationException('You are not authorized to view these grade statistics.');
        }

        $grades = $this->gradeRepository->getStatsForStudent($studentId);
        if ($grades->isEmpty()) {
            return ['message' => 'No grade data found for this student.'];
        }

        // Logic tính toán thống kê (giữ nguyên từ code của bạn)
        $groupedGrades = $grades->groupBy(['semester', 'subject_id']);
        $stats = [];
        foreach ($groupedGrades as $semester => $subjectsInSemester) {
            $semesterStats = [];
            foreach ($subjectsInSemester as $subjectId => $gradesForSubject) {
                $subject = $gradesForSubject->first()->subject;
                $semesterStats[] = [
                    'subject_name' => $subject->name,
                    'subject_code' => $subject->subject_code,
                    'average_score' => round($gradesForSubject->avg('score'), 2),
                    'total_grades' => $gradesForSubject->count(),
                    'grades_by_type' => $gradesForSubject->groupBy('type')->map(fn($typeGrades) => [
                        'count' => $typeGrades->count(),
                        'average' => round($typeGrades->avg('score'), 2)
                    ])
                ];
            }
            $stats["semester_$semester"] = $semesterStats;
        }
        return $stats;
    }
}
