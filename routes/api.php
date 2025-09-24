<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
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
        Route::post('avatar', [ProfileController::class, 'uploadAvatar']);
    });

    // Dashboard chung
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Admin only - sử dụng middleware Spatie
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('academic-years', AcademicYearController::class);
        Route::apiResource('classes', SchoolClassController::class);
        Route::apiResource('subjects', SubjectController::class);
        Route::post('users/{id}/restore', [UserController::class, 'restore'])
            ->name('users.restore');
    });

    // Teacher hoặc Admin - sử dụng RoleOrPermission middleware
    Route::middleware('role_or_permission:teacher|admin')->group(function () {
        Route::apiResource('grades', \App\Http\Controllers\GradeController::class);
        Route::apiResource('schedules', \App\Http\Controllers\ScheduleController::class);
        Route::get('my-classes', [\App\Http\Controllers\ScheduleController::class, 'myClasses']);
    });

    // Permission specific - discipline records
    Route::middleware('permission:record discipline')->group(function () {
        Route::apiResource('disciplines', \App\Http\Controllers\DisciplineController::class);
    });

    // Financial management - permission hoặc admin role
    Route::middleware('role_or_permission:admin|manage finances')->group(function () {
        Route::apiResource('invoices', \App\Http\Controllers\InvoiceController::class);
        Route::apiResource('payments', \App\Http\Controllers\PaymentController::class);
        Route::get('financial-reports', [\App\Http\Controllers\PaymentController::class, 'reports']);
    });

    // Library management
    Route::middleware('role_or_permission:admin|manage library')->group(function () {
        Route::apiResource('library-books', \App\Http\Controllers\LibraryBookController::class);
        Route::apiResource('library-transactions', \App\Http\Controllers\LibraryTransactionController::class);
    });

    // Student và Parent routes
    Route::middleware('role:student,parent')->group(function () {
        Route::get('my-grades', [\App\Http\Controllers\GradeController::class, 'myGrades']);
        Route::get('my-invoices', [\App\Http\Controllers\InvoiceController::class, 'myInvoices']);
    });

    // Parent only
    Route::middleware('role:parent')->group(function () {
        Route::get('my-children', [\App\Http\Controllers\StudentController::class, 'myChildren']);
    });

    // Events - public viewing, permission-based management
    Route::get('events', [\App\Http\Controllers\EventController::class, 'index']);
    Route::get('events/{event}', [\App\Http\Controllers\EventController::class, 'show']);
    Route::post('events/{event}/register', [\App\Http\Controllers\EventController::class, 'register']);

    Route::middleware('role_or_permission:admin|manage events')->group(function () {
        Route::post('events', [\App\Http\Controllers\EventController::class, 'store']);
        Route::put('events/{event}', [\App\Http\Controllers\EventController::class, 'update']);
        Route::delete('events/{event}', [\App\Http\Controllers\EventController::class, 'destroy']);
    });

    // Attendance - viewing và managing
    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('attendances', [\App\Http\Controllers\AttendanceController::class, 'store']);
        Route::put('attendances/{attendance}', [\App\Http\Controllers\AttendanceController::class, 'update']);
        Route::delete('attendances/{attendance}', [\App\Http\Controllers\AttendanceController::class, 'destroy']);
    });

    Route::middleware('role:student,parent,teacher,admin')->group(function () {
        Route::get('attendances', [\App\Http\Controllers\AttendanceController::class, 'index']);
        Route::get('attendances/{attendance}', [\App\Http\Controllers\AttendanceController::class, 'show']);
        Route::get('attendances/student/{student}', [\App\Http\Controllers\AttendanceController::class, 'byStudent']);
    });
});
