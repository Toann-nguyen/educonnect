<?php

namespace App\Domains\School\Application\Grades\Commands;

use App\Domains\Identity\Models\User;

final class CreateGradeCommand
{
    public function __construct(
        public readonly array $data,
        public readonly User $creator,
    ) {}
}
