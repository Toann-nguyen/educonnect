<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Schedule;
use App\Http\Resources\ScheduleResource;
use App\Models\SchoolClass;
use App\Services\Interface\ScheduleServiceInterface;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

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
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('viewAny', Schedule::class);
        return response()->json(ScheduleResource::collection(
            $this->scheduleService->getPersonalSchedule(auth()->user())
        ));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScheduleRequest $request)
    {
        $schedule = $this->scheduleService->createSchedule($request->validated());
        return response()->json(new ScheduleResource($schedule), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule)
    {
        return response()->json(new ScheduleResource(
            $schedule->load(['subject', 'teacher.profile', 'schoolClass'])
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule)
    {
        $updatedSchedule = $this->scheduleService->updateSchedule($schedule, $request->validated());
        return response()->json(new ScheduleResource($updatedSchedule));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Schedule $schedule)
    {
        $this->scheduleService->deleteSchedule($schedule);
        return response()->json(null, 204);
    }

    /**
     * Get schedule by class
     */
    public function getByClass(SchoolClass $class): JsonResponse
    {
        $schedules = $this->scheduleService->getScheduleForClass($class, auth()->user());
        return response()->json([
            'data' => ScheduleResource::collection($schedules)
        ]);
    }

    /**
     * Get personal schedule (teacher/student)
     */
    public function mySchedule(): JsonResponse
    {
        $schedules = $this->scheduleService->getPersonalSchedule(auth()->user());
        return response()->json([
            'data' => ScheduleResource::collection($schedules)
        ]);
    }

    /**
     * Get weekly schedule for a class
     */
    public function getWeeklySchedule(SchoolClass $class, Request $request): JsonResponse
    {
        $date = $request->get('date', Carbon::now()->format('Y-m-d'));
        $schedule = $this->scheduleService->getWeeklySchedule($class, $date, auth()->user());

        return response()->json([
            'data' => $schedule
        ]);
    }

    /**
     * Get classes for teacher
     */
    public function getTeacherClasses(): JsonResponse
    {
        return response()->json([
            'data' => $this->scheduleService->getTeacherClasses(auth()->user())
        ]);
    }
}
