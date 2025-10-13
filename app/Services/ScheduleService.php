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

    public function getPersonalSchedule(User $user): Collection
    {
        // Sử dụng match statement để code gọn gàng và dễ đọc hơn
        return match (true) {
            // Nếu user có vai trò 'teacher'
            $user->hasRole('teacher') => $this->scheduleRepository->getByTeacher($user),

            // Nếu user có vai trò 'student' VÀ có thông tin student liên kết
            $user->hasRole('student') && $user->student => $this->scheduleRepository->getByClass($user->student->schoolClass),

            // Đối với tất cả các trường hợp còn lại (Admin, Parent, etc.)
            default => new Collection(), // Trả về một Collection rỗng
        };
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

    public function deleteSchedule(Schedule $schedule, ?User $user = null): bool
    {
        $user ??= auth()->user();  // Lấy user từ auth nếu không pass

        if (!$user) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized.');  // 401/403
        }

        // Auth logic đơn giản (tùy chỉnh theo role)
        $canDelete = match (true) {
            $user->hasRole(['admin', 'principal']) => true,
            $user->hasRole('teacher') && $user->id === $schedule->teacher_id => true,
            $user->hasRole('student') && $user->student?->class_id === $schedule->class_id => true,  // Student chỉ xóa lịch lớp mình
            default => false,
        };

        if (!$canDelete) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not authorized to delete this schedule.');
        }

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

            //  LẤY THÔNG TIN GIÁO VIÊN

            $teacherName = null;
            $teacherId = $schedule->teacher_id; // Luôn lấy được ID từ chính bản ghi schedule

            // Chỉ xử lý nếu có giáo viên được liên kết
            if ($schedule->teacher) {
                // Ưu tiên lấy tên từ profile, nếu không có thì lấy email
                $teacherName = $schedule->teacher->profile?->full_name ?? $schedule->teacher->email;
            }


            $grid[$schedule->day_of_week - 1][$schedule->period - 1] = [
                'id' => $schedule->id,
                'subject' => $schedule->subject?->name,
                'teacher' => $teacherName, // Dùng biến đã được xử lý an toàn
                'teacher_id' => $teacherId,
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
                'teacher' => $schedule->teacher->profile->full_name ?? $schedule->teacher->email,
                'teacher_id' => $schedule->teacher_id,
                'room' => $schedule->room,
            ];
        }

        return $weekSchedule;
    }
    public function restoreSchedule(int $scheduleId): ?Schedule
    {
        $restored = $this->scheduleRepository->restore($scheduleId);
        if ($restored) {
            // Trả về bản ghi đã được khôi phục để hiển thị lại
            return Schedule::find($scheduleId);
        }
        return null;
    }
}