<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use App\Services\Interface\ScheduleServiceInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Auth\Access\AuthorizationException;

class ScheduleService implements ScheduleServiceInterface
{
    protected $scheduleRepository;

    public function __construct(ScheduleRepositoryInterface $scheduleRepository)
    {
        $this->scheduleRepository = $scheduleRepository;
    }

    public function getScheduleForClass(SchoolClass $schoolClass, User $user): array
    {
        // LOGIC PHÂN QUYỀN
        $canView = match (true) {
            $user->hasRole(['admin', 'principal']) => true,
            $user->hasRole('teacher') && $user->id === $schoolClass->homeroom_teacher_id => true,
            $user->hasRole('student') && $user->student?->class_id === $schoolClass->id => true,
            default => false,
        };

        if (!$canView) {
            throw new AuthorizationException('You are not authorized to view this schedule.');
        }

        $schedules = $this->scheduleRepository->getByClass($schoolClass);
        return $this->formatScheduleGrid($schedules);
    }

    public function getPersonalSchedule(User $user): array
    {
        if ($user->hasRole('teacher')) {
            $schedules = $this->scheduleRepository->getByTeacher($user);
        } elseif ($user->hasRole('student') && $user->student) {
            $schedules = $this->scheduleRepository->getByClass($user->student->schoolClass);
        } else {
            return [];
        }

        return $this->formatScheduleGrid($schedules);
    }

    public function getWeeklySchedule(SchoolClass $schoolClass, string $date, User $user): array
    {
        $this->authorizeView($schoolClass, $user);

        $schedules = $this->scheduleRepository->getByClassAndWeek($schoolClass, Carbon::parse($date));
        return $this->formatWeeklySchedule($schedules);
    }

    public function getTeacherClasses(User $user): Collection
    {
        if (!$user->hasRole('teacher')) {
            throw new AuthorizationException('Only teachers can access their class list.');
        }

        return $this->scheduleRepository->getTeacherClasses($user);
    }

    public function createSchedule(array $data): Schedule
    {
        return $this->scheduleRepository->create($data);
    }

    public function updateSchedule(Schedule $schedule, array $data): Schedule
    {
        return $this->scheduleRepository->update($schedule->id, $data);
    }

    public function deleteSchedule(Schedule $schedule): bool
    {
        return $this->scheduleRepository->delete($schedule->id);
    }

    protected function authorizeView(SchoolClass $schoolClass, User $user): void
    {
        $canView = match (true) {
            $user->hasRole(['admin', 'principal']) => true,
            $user->hasRole('teacher') && $user->id === $schoolClass->homeroom_teacher_id => true,
            $user->hasRole('student') && $user->student?->class_id === $schoolClass->id => true,
            default => false,
        };

        if (!$canView) {
            throw new AuthorizationException('You are not authorized to view this schedule.');
        }
    }

    protected function formatScheduleGrid(Collection $schedules): array
    {
        $grid = array_fill(0, 7, array_fill(0, 10, null));

        foreach ($schedules as $schedule) {
            $grid[$schedule->day_of_week - 1][$schedule->period - 1] = [
                'id' => $schedule->id,
                'subject' => $schedule->subject->name,
                'teacher' => $schedule->teacher->name,
                'room' => $schedule->room,
            ];
        }

        return $grid;
    }

    protected function formatWeeklySchedule(Collection $schedules): array
    {
        $weekSchedule = [];

        foreach ($schedules as $schedule) {
            $date = $schedule->date->format('Y-m-d');
            if (!isset($weekSchedule[$date])) {
                $weekSchedule[$date] = array_fill(0, 10, null);
            }

            $weekSchedule[$date][$schedule->period - 1] = [
                'id' => $schedule->id,
                'subject' => $schedule->subject->name,
                'teacher' => $schedule->teacher->name,
                'room' => $schedule->room,
            ];
        }

        return $weekSchedule;
    }
}
