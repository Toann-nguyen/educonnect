<?php

namespace App\Repositories\Contracts;

use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ScheduleRepositoryInterface
{
    public function getByClass(SchoolClass $schoolClass): Collection;
    public function getByTeacher(User $user): Collection;
    public function create(array $data): \App\Models\Schedule;
    public function update(int $scheduleId, array $data): \App\Models\Schedule;
    public function delete(int $scheduleId): bool;
    // tim ban da xoa mem
    public function findTrashed(int $scheduleId): ?\App\Models\Schedule;
    // restore lai ben soft delete
    public function restore(int $scheduleId): bool;
}
