<?php

namespace App\Domains\School\Services\Interface;

use App\Domains\School\Models\Schedule;
use App\Domains\School\Models\SchoolClass;
use App\Domains\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ScheduleServiceInterface
{
    public function getScheduleForClass(SchoolClass $schoolClass, User $user): array;
    public function getPersonalSchedule(User $user): Collection;
    public function getWeeklySchedule(SchoolClass $schoolClass, string $date, User $user): array;
    public function getTeacherClasses(User $user): Collection;
    public function createSchedule(array $data): Schedule;
    public function updateSchedule(Schedule $schedule, array $data): Schedule;
    public function deleteSchedule(Schedule $schedule): bool;
    public function restoreSchedule(int $scheduleId): ?Schedule;
}