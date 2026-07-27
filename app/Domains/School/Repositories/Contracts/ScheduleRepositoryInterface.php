<?php

namespace App\Domains\School\Repositories\Contracts;

use App\Domains\Identity\Models\User;
use App\Domains\School\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;

interface ScheduleRepositoryInterface
{
    public function getByClass(\App\Domains\School\Models\SchoolClass $schoolClass): Collection;
    public function getByTeacher(User $user): Collection;
    public function findTrashed(int $scheduleId): ?Schedule;
    public function restore(int $scheduleId): bool;
    public function create(array $data): Schedule;
    public function update(int $id, array $data): Schedule;
    public function delete(int $scheduleId): bool;
    public function getTeacherClasses(User $user): Collection;
}
