<?php

namespace App\Services\Interface;

use App\Domains\Identity\Models\User;

interface StudentServiceInterface
{
    public function getChildrenOfParent(User $parent);
}
