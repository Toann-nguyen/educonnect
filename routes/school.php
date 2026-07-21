<?php

use App\Domains\School\Http\Controllers\AcademicYearController;
use App\Domains\School\Http\Controllers\SchoolClassController;
use App\Domains\School\Http\Controllers\SubjectController;
use App\Domains\School\Http\Controllers\ScheduleController;
use App\Domains\School\Http\Controllers\GradeController;
use App\Domains\School\Http\Controllers\DisciplineController;
use App\Domains\School\Http\Controllers\DisciplineTypeController;
use App\Domains\School\Http\Controllers\ConductScoreController;
use App\Domains\School\Http\Controllers\StudentController;
use App\Domains\School\Http\Controllers\EventController;
use App\Domains\School\Http\Controllers\EventRegistrationController;
use App\Domains\School\Http\Controllers\LibraryBookController;
use App\Domains\School\Http\Controllers\LibraryTransactionController;
use App\Domains\School\Http\Controllers\DashBoardController;
use App\Domains\School\Http\Controllers\AttendanceController;
use App\Domains\School\Http\Controllers\StudentGuardianController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.jwt'])->group(function () {

    Route::get('dashboard', [DashBoardController::class, 'index']);

    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('academic-years', AcademicYearController::class);
        Route::apiResource('classes', SchoolClassController::class);
        Route::apiResource('subjects', SubjectController::class);
    });

    Route::prefix('schedules')->group(function () {
        Route::get('class/{class}', [ScheduleController::class, 'getByClass']);
        Route::get('class/{class}/week', [ScheduleController::class, 'getWeeklySchedule']);
        Route::get('my', [ScheduleController::class, 'mySchedule'])->middleware('role:teacher|student');
        Route::get('my-classes', [ScheduleController::class, 'getTeacherClasses'])->middleware('role:teacher');
        Route::middleware(['role:admin|principal|teacher'])->group(function () {
            Route::get('/', [ScheduleController::class, 'index']);
            Route::post('/', [ScheduleController::class, 'store']);
            Route::get('{schedule}', [ScheduleController::class, 'show']);
            Route::put('{schedule}', [ScheduleController::class, 'update']);
            Route::delete('{schedule}', [ScheduleController::class, 'destroy']);
            Route::post('{id}/restore', [ScheduleController::class, 'restore']);
        });
    });

    Route::middleware('role:student|parent')->group(function () {
        Route::get('my-grades', [GradeController::class, 'myGrades']);
    });
    Route::middleware('role_or_permission:teacher|admin')->group(function () {
        Route::apiResource('grades', GradeController::class);
    });

    Route::prefix('disciplines')->group(function () {
        Route::get('/', [DisciplineController::class, 'index']);
        Route::get('my', [DisciplineController::class, 'my'])->middleware('role:student|parent');
        Route::get('class/{classId}', [DisciplineController::class, 'byClass'])->middleware('role:admin|principal|teacher');
        Route::get('statistics', [DisciplineController::class, 'statistics'])->middleware('role:admin|principal');
        Route::get('student/{studentId}', [DisciplineController::class, 'byStudent'])->middleware('role:admin|principal|teacher');
        Route::get('export', [DisciplineController::class, 'export'])->middleware('role:admin|principal');
        Route::get('{discipline}', [DisciplineController::class, 'show']);
        Route::post('/', [DisciplineController::class, 'store'])->middleware('permission:record discipline');
        Route::put('{discipline}', [DisciplineController::class, 'update']);
        Route::delete('{discipline}', [DisciplineController::class, 'destroy'])->middleware('role:admin|principal');
        Route::post('{discipline}/approve', [DisciplineController::class, 'approve'])->middleware('role:admin|principal');
        Route::post('{discipline}/reject', [DisciplineController::class, 'reject'])->middleware('role:admin|principal');
        Route::post('{discipline}/appeal', [DisciplineController::class, 'appeal'])->middleware('role:student|parent');
    });

    Route::prefix('discipline-types')->middleware('role:admin|principal')->group(function () {
        Route::get('/', [DisciplineTypeController::class, 'index'])->withoutMiddleware('role:admin|principal');
        Route::get('{disciplineType}', [DisciplineTypeController::class, 'show'])->withoutMiddleware('role:admin|principal');
        Route::post('/', [DisciplineTypeController::class, 'store']);
        Route::put('{disciplineType}', [DisciplineTypeController::class, 'update']);
        Route::delete('{disciplineType}', [DisciplineTypeController::class, 'destroy']);
    });

    Route::prefix('conduct-scores')->group(function () {
        Route::get('my', [ConductScoreController::class, 'my'])->middleware('role:student|parent');
        Route::get('class/{classId}', [ConductScoreController::class, 'byClass'])->middleware('role:admin|principal|teacher');
        Route::get('student/{studentId}', [ConductScoreController::class, 'byStudent'])->middleware('role:admin|principal|teacher');
        Route::post('/', [ConductScoreController::class, 'store'])->middleware('role:admin|principal|teacher');
        Route::put('{conductScore}', [ConductScoreController::class, 'update'])->middleware('role:teacher|admin|principal');
        Route::post('{conductScore}/approve', [ConductScoreController::class, 'approve'])->middleware('role:admin|principal');
        Route::post('recalculate', [ConductScoreController::class, 'recalculate'])->middleware('role:admin|principal');
    });

    Route::middleware('role:parent')->group(function () {
        Route::get('my-children', [StudentController::class, 'myChildren']);
    });
});
