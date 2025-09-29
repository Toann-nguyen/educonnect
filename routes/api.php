<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\LibraryBookController;
use App\Http\Controllers\LibraryTransactionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DashBoardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login']);
// Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
// Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

// Auth routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});


// Protected routes
Route::middleware(['auth:sanctum'])->group(function () {

    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
    });

    // Profile - tất cả user đăng nhập
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
    });

    // Dashboard chung
    Route::get('dashboard', [DashBoardController::class, 'index']);

    // Admin only
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('academic-years', AcademicYearController::class);
        Route::apiResource('classes', SchoolClassController::class);
        Route::apiResource('subjects', SubjectController::class);
        Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
    });

    // =============================================================
    // == SCHEDULE ROUTES (Thời khóa biểu)
    // =============================================================
    Route::prefix('schedules')->group(function () {

        // Public (tất cả user đăng nhập, phân quyền chi tiết trong service)
        Route::get('class/{class}', [ScheduleController::class, 'getByClass'])->name('schedules.by-class');
        Route::get('class/{class}/week', [ScheduleController::class, 'getWeeklySchedule'])->name('schedules.by-class.week');

        // My schedule - Teacher, Student
        Route::get('my', [ScheduleController::class, 'mySchedule'])
            ->middleware('role:teacher|student')
            ->name('schedules.my');

        // My classes - Teacher only
        Route::get('my-classes', [ScheduleController::class, 'getTeacherClasses'])
            ->middleware('role:teacher')
            ->name('schedules.my-classes');

        // CRUD cho admin & principal
        Route::middleware(['role:admin|principal|teacher'])->group(function () {
            Route::get('/', [ScheduleController::class, 'index']);
            Route::post('/', [ScheduleController::class, 'store']);
            Route::get('{schedule}', [ScheduleController::class, 'show']);
            Route::put('{schedule}', [ScheduleController::class, 'update']);
            Route::patch('{schedule}', [ScheduleController::class, 'update']);
            Route::delete('{schedule}', [ScheduleController::class, 'destroy']);
            Route::post('{id}/restore', [ScheduleController::class, 'restore'])->name('schedules.restore');
        });
    });

    // Grades (Teacher/Admin)
    Route::middleware('role_or_permission:teacher|admin')->group(function () {
        Route::apiResource('grades', GradeController::class);
    });

    // Discipline records
    Route::middleware('permission:record discipline')->group(function () {
        Route::apiResource('disciplines', DisciplineController::class);
    });

    // Financial management
    Route::middleware('role_or_permission:admin|manage finances')->group(function () {
        Route::apiResource('invoices', InvoiceController::class);
        Route::apiResource('payments', PaymentController::class);
        Route::get('financial-reports', [PaymentController::class, 'reports']);
    });

    // Library
    Route::middleware('role_or_permission:admin|manage library')->group(function () {
        Route::apiResource('library-books', LibraryBookController::class);
        Route::apiResource('library-transactions', LibraryTransactionController::class);
    });

    // Student & Parent thi se hien thong thong tin cho dung voi role do
    // vi du role == student thi se hien thi thong tin cua dung 1 student do thoi
    Route::middleware('role:student|parent')->group(function () {
        Route::get('my-grades', [GradeController::class, 'myGrades']);
        Route::get('my-invoices', [InvoiceController::class, 'myInvoices']);
    });

    // Parent only
    Route::middleware('role:parent')->group(function () {
        Route::get('my-children', [StudentController::class, 'myChildren']);
    });

    // Events
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);
    Route::post('events/{event}/register', [EventController::class, 'register']);

    Route::middleware('role_or_permission:admin|manage events')->group(function () {
        Route::post('events', [EventController::class, 'store']);
        Route::put('events/{event}', [EventController::class, 'update']);
        Route::delete('events/{event}', [EventController::class, 'destroy']);
    });

    // Attendance
    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('attendances', [AttendanceController::class, 'store']);
        Route::put('attendances/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy']);
    });

    Route::middleware('role:student,parent,teacher,admin')->group(function () {
        Route::get('attendances', [AttendanceController::class, 'index']);
        Route::get('attendances/{attendance}', [AttendanceController::class, 'show']);
        Route::get('attendances/student/{student}', [AttendanceController::class, 'byStudent']);
    });
});
