<?php

namespace App\Domains\School\Application\Grades\Queries;

use App\Domains\School\Services\Interface\GradeServiceInterface;

final class GetMyGradesQueryHandler
{
    public function __construct(private readonly GradeServiceInterface $grades) {}

    public function handle(GetMyGradesQuery $query): mixed
    {
        return $this->grades->getPersonalGrades($query->user);
    }
}
