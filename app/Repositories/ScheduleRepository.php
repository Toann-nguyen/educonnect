<?php

namespace App\Repositories;

use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Repositories\Interfaces\ScheduleRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleRepository implements ScheduleRepositoryInterface
{
    protected $model;
    protected $classModel;

    public function __construct(Schedule $schedule, SchoolClass $schoolClass)
    {
        $this->model = $schedule;
        $this->classModel = $schoolClass;
    }

    public function all()
    {
        return $this->model->with(['subject', 'teacher', 'class'])->get();
    }

    public function find($id)
    {
        return $this->model->with(['subject', 'teacher', 'class'])->findOrFail($id);
    }

    public function findByClass($classId)
    {
        return $this->model
            ->with(['subject', 'teacher.profile'])
            ->where('class_id', $classId)
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get();
    }

    public function findByTeacher($teacherId)
    {
        return $this->model
            ->with(['subject', 'class'])
            ->where('teacher_id', $teacherId)
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get();
    }

    public function findByStudent($studentId)
    {
        return $this->model
            ->with(['subject', 'teacher', 'class'])
            ->whereHas('class.students', function ($query) use ($studentId) {
                $query->where('student_id', $studentId);
            })
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get();
    }

    public function findByClassAndWeek($classId, $date)
    {
        $startOfWeek = Carbon::parse($date)->startOfWeek();
        $endOfWeek = Carbon::parse($date)->endOfWeek();

        return $this->model
            ->with(['subject', 'teacher'])
            ->where('class_id', $classId)
            ->whereBetween('date', [$startOfWeek, $endOfWeek])
            ->orderBy('date')
            ->orderBy('period')
            ->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    public function update($id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $schedule = $this->model->findOrFail($id);
            $schedule->update($data);
            return $schedule;
        });
    }

    public function delete($id)
    {
        return DB::transaction(function () use ($id) {
            return $this->model->findOrFail($id)->delete();
        });
    }

    public function getTeacherClasses($teacherId)
    {
        return $this->classModel
            ->whereHas('schedules', function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            })
            ->with(['schedules' => function ($query) use ($teacherId) {
                $query->where('teacher_id', $teacherId);
            }])
            ->get();
    }
}
