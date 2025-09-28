<?php

namespace App\Services\Interface;

use App\Models\User;

interface StudentServiceInterface
{
    public function getChildrenOfParent(User $parent);
}
