<?php

namespace App\Services;

use App\Models\Discipline;
use App\Models\DisciplineAppeal;
use App\Models\DisciplineType;
use App\Models\User;
use App\Repositories\Contracts\DisciplineRepositoryInterface;
use App\Services\Interface\DisciplineServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DisciplineService implements DisciplineServiceInterface
{
    protected $disciplineRepository;

    public function __construct(DisciplineRepositoryInterface $disciplineRepository)
    {
        $this->disciplineRepository = $disciplineRepository;
    }

    public function getAllDisciplines(array $filters): LengthAwarePaginator
    {
        return $this->disciplineRepository->getAll($filters);
    }

    public function getMyDisciplines(User $user, array $filters = []): LengthAwarePaginator
    {
        // If student, return their records
        if ($user->hasRole('student')) {
            return $this->disciplineRepository->getByStudentUserId($user->id);
        }

        // If parent, allow optional student_id filter (to select a specific child)
        if ($user->hasRole('parent')) {
            // If a specific student_id is requested, ensure the student belongs to this parent
            if (isset($filters['student_id'])) {
                $studentId = (int) $filters['student_id'];
                $owns = $user->guardianStudents()->where('students.id', $studentId)->exists();
                if (!$owns) {
                    // Return empty paginator (403 could be used, but we keep API consistent)
                    return $this->disciplineRepository->getAll(['status' => 'non_existent']);
                }

                return $this->disciplineRepository->getByStudentId($studentId, ['status' => 'confirmed']);
            }

            // Default: return all children disciplines
            return $this->disciplineRepository->getByParentUserId($user->id);
        }

        // Fallback to empty result
        return $this->disciplineRepository->getAll(['status' => 'non_existent']);
    }

    public function getDisciplinesByClass(int $classId, array $filters): LengthAwarePaginator
    {
        return $this->disciplineRepository->getByClassId($classId, $filters);
    }

    public function getDisciplinesByStudent(int $studentId, array $filters): LengthAwarePaginator
    {
        return $this->disciplineRepository->getByStudentId($studentId, $filters);
    }

    /**
     * Tạo một bản ghi kỷ luật mới, bổ sung các thông tin cần thiết.
     *
     * @param array $data Dữ liệu đã được validate từ request
     * @param User $reporter Người dùng đang thực hiện hành động (người báo cáo)
     * @return Discipline
     */
    public function createDiscipline(array $data, User $reporter): Discipline
    {
        // Sử dụng transaction để đảm bảo tất cả các thao tác CSDL thành công hoặc thất bại cùng lúc.
        // Đây là best practice cho các hành động "ghi" dữ liệu.
        return DB::transaction(function () use ($data, $reporter) {

            // Bước 1: Lấy thông tin phụ thuộc từ CSDL
            // Tìm loại vi phạm để lấy điểm trừ mặc định.
            // dùng findOrFail() để đảm bảo nếu `discipline_type_id` không hợp lệ,
            // request sẽ dừng lại ngay lập tức và trả về lỗi 404.
            $type = DisciplineType::findOrFail($data['discipline_type_id']);

            // Bước 2: Xây dựng một mảng dữ liệu "sạch" và đầy đủ
            // Chỉ lấy những gì cần thiết từ $data và bổ sung các thông tin từ server.
            $disciplineData = [
                // Dữ liệu từ client đã được validate
                'student_id' => $data['student_id'],
                'discipline_type_id' => $data['discipline_type_id'],
                'incident_date' => $data['incident_date'],
                'incident_location' => $data['incident_location'] ?? null,
                'description' => $data['description'] ?? null,

                // Dữ liệu được bổ sung bởi logic nghiệp vụ ở server
                'reporter_user_id' => $reporter->id, // Lấy ID của người đang đăng nhập
                'status' => 'pending', // Luôn đặt trạng thái ban đầu là 'reported'
                'penalty_points' => $type->default_penalty_points, // Lấy điểm trừ từ loại vi phạm
            ];

            // Bước 3: Gọi Repository để thực hiện việc lưu trữ
            // Truyền vào mảng dữ liệu đã được làm sạch và chuẩn hóa hoàn toàn.
            return $this->disciplineRepository->create($disciplineData);
        });
    }

    public function updateDiscipline(Discipline $discipline, array $data): Discipline
    {
        return DB::transaction(function () use ($discipline, $data) {
            return $this->disciplineRepository->update($discipline->id, $data);
        });
    }

    public function deleteDiscipline(Discipline $discipline): bool
    {
        return $this->disciplineRepository->delete($discipline->id);
    }

    public function approveDiscipline(Discipline $discipline, User $reviewer, ?string $note = null): Discipline
    {
        return DB::transaction(function () use ($discipline, $reviewer, $note) {
            $data = [
                'status' => 'confirmed',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ];

            $updated = $this->disciplineRepository->update($discipline->id, $data);

            // ✅ Đảm bảo return data mới
            return $updated;
        });
    }

    public function rejectDiscipline(Discipline $discipline, User $reviewer, string $reason): Discipline
    {
        return DB::transaction(function () use ($discipline, $reviewer, $reason) {
            $data = [
                'status' => 'rejected',
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $reason,
            ];

            $updated = $this->disciplineRepository->update($discipline->id, $data);

            // TODO: Trigger notification to reporter

            return $updated;
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

            $this->disciplineRepository->update($discipline->id, ['status' => 'appealed']);

            // TODO: Trigger notification to reviewer

            return true;
        });
    }

    public function getStatistics(array $filters): array
    {
        $stats = $this->disciplineRepository->getStatistics($filters);
        $topViolations = $this->disciplineRepository->getTopViolations(10, $filters);

        return array_merge($stats, [
            'top_violations' => $topViolations->toArray(),
        ]);
    }

    public function canAccess(User $user, Discipline $discipline): bool
    {
        // Admin/Principal can view all
        if ($user->hasAnyRole(['admin', 'principal'])) {
            return true;
        }

        // Teacher can view if reporter or homeroom teacher
        if ($user->hasRole('teacher')) {
            if ($discipline->reporter_user_id === $user->id) {
                return true;
            }

            $isHomeroom = $discipline->student->schoolClass
                && $discipline->student->schoolClass->homeroom_teacher_id === $user->id;

            return $isHomeroom;
        }

        // Student can only view their own confirmed records
        if ($user->hasRole('student')) {
            return $discipline->student->user_id === $user->id
                && $discipline->status === 'confirmed';
        }

        // Parent can view their child's confirmed records
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
        // Admin/Principal can modify all
        if ($user->hasAnyRole(['admin', 'principal'])) {
            return true;
        }

        // Teacher can only modify their own pending records
        if ($user->hasRole('teacher')) {
            return $discipline->reporter_user_id === $user->id
                && $discipline->status === 'pending';
        }

        return false;
    }
}