<?php

namespace App\Services\Interface;

interface GradeServiceInterface
{
    public function getPersonalGrades(\App\Models\User $user);
}
