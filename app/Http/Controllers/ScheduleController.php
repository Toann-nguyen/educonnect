<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\SchoolClass;
use App\Services\Interface\ScheduleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    protected $scheduleService;

    public function __construct(ScheduleServiceInterface $scheduleService)
    {
        $this->scheduleService = $scheduleService;
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Schedule::class, 'schedule', [
            'except' => ['getByClass', 'mySchedule', 'getWeeklySchedule', 'getTeacherClasses']
        ]);
    }

    /**
     * Display a listing of ALL schedules (with filters)
     * GET /api/schedules
     * Admin/Principal can see all, Teachers see their classes
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Schedule::class);

        // Trả về toàn bộ thời khóa biểu theo từng lớp, kèm danh sách học sinh của lớp
        $classes = SchoolClass::with([
            'academicYear:id,name',
            'homeroomTeacher.profile:id,user_id,full_name',
            'students.user.profile:id,user_id,full_name',
            'schedules' => function ($q) {
                $q->with(['subject:id,name', 'teacher.profile:id,user_id,full_name'])
                    ->orderBy('day_of_week')
                    ->orderBy('period');
            }
        ])->orderBy('name')->get();

        // Chuẩn hóa dữ liệu trả về
        $data = $classes->map(function ($class) {
            return [
                'id' => $class->id,
                'name' => $class->name,
                'academic_year' => $class->academicYear?->name,
                'homeroom_teacher' => $class->homeroomTeacher?->profile?->full_name ?? $class->homeroomTeacher?->email,
                'students' => $class->students->map(function ($student) {
                    return [
                        'id' => $student->id,
                        'user_id' => $student->user_id,
                        'name' => $student->user?->profile?->full_name ?? $student->user?->email,
                        'student_code' => $student->student_code,
                        'status' => $student->status,
                    ];
                }),
                'schedules' => $class->schedules->map(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'day_of_week' => $schedule->day_of_week,
                        'period' => $schedule->period,
                        'room' => $schedule->room,
                        'subject' => $schedule->subject?->name,
                        'teacher' => $schedule->teacher?->profile?->full_name ?? $schedule->teacher?->email,
                        'teacher_id' => $schedule->teacher_id,
                    ];
                })
            ];
        });

        return response()->json([
            'message' => 'All class schedules with students retrieved successfully',
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $schedule = $this->scheduleService->createSchedule($request->validated());
        return response()->json([
            'message' => 'Schedule created successfully',
            'data' => new ScheduleResource($schedule)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule): JsonResponse
    {
        return response()->json([
            'message' => 'Schedule retrieved successfully',
            'data' => new ScheduleResource(
                $schedule->load(['subject', 'teacher.profile', 'schoolClass'])
            )
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $updatedSchedule = $this->scheduleService->updateSchedule($schedule, $request->validated());
        return response()->json([
            'message' => 'Schedule updated successfully',
            'data' => new ScheduleResource($updatedSchedule)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        // Load với trashed để handle nếu đã soft delete
        $schedule = Schedule::withTrashed()->findOrFail($schedule->id);

        if ($schedule->trashed()) {
            return response()->json(['message' => 'Schedule already deleted (in trash).'], 409);  // Conflict, không xóa lại
        }

        $success = $this->scheduleService->deleteSchedule($schedule);
        if (!$success) {
            return response()->json(['message' => 'Delete failed: Schedule not found.'], 500);
        }

        return response()->json([
            'message' => 'Schedule deleted successfully'
        ], 200);
    }

    /**
     * Restore schedule with id in schedules
     */
    public function restore($id): JsonResponse
    {
        $this->authorize('restore', Schedule::withTrashed()->find($id));
        $schedule = $this->scheduleService->restoreSchedule($id);

        if ($schedule) {
            return response()->json([
                'message' => 'Schedule restored successfully.',
                'data' => new ScheduleResource($schedule)
            ]);
        }

        return response()->json(['message' => 'Schedule not found in trash.'], 404);
    }

    /**
     * Get schedule by class
     * GET /api/schedules/class/{class}
     */
    public function getByClass(SchoolClass $class, Request $request): JsonResponse
    {
        $schedules = $this->scheduleService->getScheduleForClass($class, auth()->user());
        return response()->json([
            'message' => 'Class schedule retrieved successfully',
            'data' => $schedules
        ]);
    }

    /**
     * Get personal schedule (teacher/student) - MY SCHEDULE
     * GET /api/schedules/my
     */
    public function mySchedule(): JsonResponse
    {
        $schedules = $this->scheduleService->getPersonalSchedule(auth()->user());
        return response()->json([
            'message' => 'Personal schedule retrieved successfully',
            'data' => ScheduleResource::collection($schedules)
        ]);
    }

    /**
     * Get weekly schedule for a class
     * GET /api/schedules/class/{class}/week
     */
    public function getWeeklySchedule(SchoolClass $class, Request $request): JsonResponse
    {
        return $this->getByClass($class, $request);
    }

    /**
     * Get classes for teacher
     * GET /api/schedules/my-classes
     */
    public function getTeacherClasses(): JsonResponse
    {
        // dd(2);
        return response()->json([
            'message' => 'Teacher classes retrieved successfully',
            'data' => $this->scheduleService->getTeacherClasses(auth()->user())
        ]);
    }
}