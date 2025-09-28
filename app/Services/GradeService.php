<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\GradeRepositoryInterface;
use App\Services\Interface\GradeServiceInterface;

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
}
