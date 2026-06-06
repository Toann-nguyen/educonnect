<?php

namespace App\Services;

use App\Services\Interface\StudentServiceInterface;
use \App\Models\User;

class StudentService implements StudentServiceInterface
{
    public function getChildrenOfParent(User $parent)
    {
        return $parent->guardianStudents()->with(['user.profile', 'schoolClass'])->get();
    }
}
