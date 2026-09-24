<?php

namespace App\Domains\School\Application\Grades\Queries;

use App\Domains\Identity\Models\User;

final class GetMyGradesQuery
{
    public function __construct(public readonly User $user) {}
}
