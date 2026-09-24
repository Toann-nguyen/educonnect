<?php

namespace App\Domains\School\Application\Grades\Commands;

use App\Domains\School\Models\Grade;
use App\Domains\School\Services\Interface\GradeServiceInterface;

final class CreateGradeCommandHandler
{
    public function __construct(private readonly GradeServiceInterface $grades) {}

    public function handle(CreateGradeCommand $command): Grade
    {
        return $this->grades->createGrade($command->data, $command->creator);
    }
}
