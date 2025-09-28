<?php

namespace App\Repositories\Contracts;

use  \Illuminate\Database\Eloquent\Collection;

interface GradeRepositoryInterface
{
    public function getByStudentId(int $studentId);
}
