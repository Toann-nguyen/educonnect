<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\GradeRepositoryInterface;
use \App\Models\Grade;

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
}
