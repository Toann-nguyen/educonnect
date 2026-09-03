<?php

namespace App\Domains\School\Services\Interface;

use App\Domains\Identity\Models\User;

interface StudentServiceInterface
{
    public function getChildrenOfParent(User $parent);
}
