<?php

namespace App\Repositories\Eloquent;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Models\User;
use App\Repositories\Contracts\ScheduleRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ScheduleRepository implements ScheduleRepositoryInterface
{
    protected $model;

    public function __construct(Schedule $schedule)
    {
        $this->model = $schedule;
    }

    public function getByClass(SchoolClass $schoolClass): Collection
    {
        return $this->model->where('class_id', $schoolClass->id)
            ->with(['subject:id,name', 'teacher.profile:id,user_id,full_name']) // Eager load để tối ưu
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get();
    }

    public function getByTeacher(User $user): Collection
    {
        return $this->model->where('teacher_id', $user->id)
            ->with(['subject:id,name', 'schoolClass:id,name']) // Eager load
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get();
    }

    public function findTrashed(int $scheduleId): ?Schedule
    {
        // onlyTrashed() chỉ tìm trong các bản ghi đã bị xóa mềm
        return Schedule::onlyTrashed()->find($scheduleId);
    }

    public function restore(int $scheduleId): bool
    {
        $schedule = $this->findTrashed($scheduleId);
        if ($schedule) {
            return $schedule->restore();
        }
        return false;
    }

    public function create(array $data): Schedule
    {
        return $this->model->create($data);
    }

    public function update(int $scheduleId, array $data): Schedule
    {
        $schedule = $this->model->findOrFail($scheduleId);
        $schedule->update($data);
        return $schedule;
    }

    public function delete(int $scheduleId): bool
    {
        return $this->model->destroy($scheduleId);
    }
}
