<?php

namespace App\Services\Interface;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
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
