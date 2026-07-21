<?php

namespace App\Domains\School\Services\Interface;

use App\Domains\School\Models\Discipline;
use App\Domains\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface DisciplineServiceInterface
{
    /**
     * Lấy danh sách kỷ luật với filters
     */
    public function getAllDisciplines(array $filters): LengthAwarePaginator;

    /**
     * Lấy kỷ luật của học sinh/con của phụ huynh
     */
    /**
     * Lấy kỷ luật của học sinh/con của phụ huynh
     *
     * @param User $user
     * @param array $filters Optional filters (e.g. 'student_id')
     */
    public function getMyDisciplines(User $user, array $filters = []): LengthAwarePaginator;

    /**
     * Lấy kỷ luật theo lớp
     */
    public function getDisciplinesByClass(int $classId, array $filters): LengthAwarePaginator;

    /**
     * Lấy kỷ luật của một học sinh
     */
    public function getDisciplinesByStudent(int $studentId, array $filters): LengthAwarePaginator;

    /**
     * Tạo một bản ghi kỷ luật mới.
     *
     * @param array $data Dữ liệu đã được validate
     * @param User $reporter Người dùng thực hiện hành động
     * @return Discipline
     */
    public function createDiscipline(array $data, User $reporter): Discipline;

    /**
     * Cập nhật bản ghi kỷ luật
     */
    public function updateDiscipline(Discipline $discipline, array $data): Discipline;

    /**
     * Xóa mềm bản ghi kỷ luật
     */
    public function deleteDiscipline(Discipline $discipline): bool;

    /**
     * Duyệt bản ghi kỷ luật
     */
    public function approveDiscipline(Discipline $discipline, User $reviewer, ?string $note = null): Discipline;

    /**
     * Từ chối bản ghi kỷ luật
     */
    public function rejectDiscipline(Discipline $discipline, User $reviewer, string $reason): Discipline;

    /**
     * Tạo khiếu nại
     */
    public function createAppeal(Discipline $discipline, User $appellant, string $reason, ?array $evidence = null): bool;

    /**
     * Thống kê kỷ luật
     */
    public function getStatistics(array $filters): array;

    /**
     * Kiểm tra quyền truy cập
     */
    public function canAccess(User $user, Discipline $discipline): bool;

    /**
     * Kiểm tra quyền chỉnh sửa
     */
    public function canModify(User $user, Discipline $discipline): bool;
}
