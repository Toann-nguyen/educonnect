<?php

namespace App\Domains\School\Services;

use App\Domains\School\Services\Interface\StudentServiceInterface;
use App\Domains\Identity\Models\User;

class StudentService implements StudentServiceInterface
{
    public function getChildrenOfParent(User $parent)
    {
        return $parent->guardianStudents()->with(['user.profile', 'schoolClass'])->get();
    }
}
