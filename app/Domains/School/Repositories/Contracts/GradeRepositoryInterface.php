<?php

namespace App\Domains\School\Repositories\Contracts;

use  \Illuminate\Database\Eloquent\Collection;
use App\Domains\School\Models\Grade;
use App\Domains\Identity\Models\User;

interface GradeRepositoryInterface
{
    // lay diem hoc sinh cho token student
    public function getByStudentId(int $studentId);
    public function getAll(array $filters, User $user);
    public function create(array $data);
    public function update(int $gradeId, array $data);
    public function delete(int $gradeId);
    public function getByClass(int $classId, array $filters, User $user);
    public function getStatsForStudent(int $studentId);
}
