<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FeeTypeController;
use App\Http\Controllers\QrController;
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
// ============================================================================
// PUBLIC ROUTES (No Authentication Required)
// ============================================================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

Route::post('qrcode', [QrController::class, 'testQr']);
// ============================================================================
// PROTECTED ROUTES (Authentication Required)
// ============================================================================
Route::middleware(['auth:sanctum'])->group(function () {

    // ------------------------------------------------------------------------
    // AUTH & PROFILE ROUTES
    // ------------------------------------------------------------------------
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
    // ------------------------------------------------------------------------
    // DASHBOARD chung
    // ------------------------------------------------------------------------
    Route::get('dashboard', [DashBoardController::class, 'index']);
    // ------------------------------------------------------------------------
    // ADMIN ONLY ROUTES
    // ------------------------------------------------------------------------
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('academic-years', AcademicYearController::class);
        Route::apiResource('classes', SchoolClassController::class);
        Route::apiResource('subjects', SubjectController::class);
        Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
    });

    // ------------------------------------------------------------------------
    // SCHEDULE ROUTES
    // ------------------------------------------------------------------------
    Route::prefix('schedules')->group(function () {
        // Public endpoints (authorization in service layer)
        Route::get('class/{class}', [ScheduleController::class, 'getByClass'])->name('schedules.by-class');
        Route::get('class/{class}/week', [ScheduleController::class, 'getWeeklySchedule'])->name('schedules.by-class.week');

        // My schedule (Teacher/Student)
        Route::get('my', [ScheduleController::class, 'mySchedule'])
            ->middleware('role:teacher|student')
            ->name('schedules.my');

        // My classes (Teacher only)
        Route::get('my-classes', [ScheduleController::class, 'getTeacherClasses'])
            ->middleware('role:teacher')
            ->name('schedules.my-classes');

        // CRUD (Admin/Principal/Teacher)
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

    // ------------------------------------------------------------------------
    // GRADE ROUTES
    // ------------------------------------------------------------------------
    Route::middleware('role:student|parent')->group(function () {
        Route::get('my-grades', [GradeController::class, 'myGrades']);
    });

    Route::middleware('role_or_permission:teacher|admin')->group(function () {
        Route::apiResource('grades', GradeController::class);
    });

    // ------------------------------------------------------------------------
    // FEE TYPES ROUTES (chuc nang lien quan cac loai hoc phi)
    // ------------------------------------------------------------------------
    // All authenticated users can view
    Route::get('fee-types', [FeeTypeController::class, 'index']);
    Route::get('fee-types/{feeType}', [FeeTypeController::class, 'show']);

    // Admin/Principal/Accountant only
    Route::middleware('role:admin|principal|accountant')->group(function () {
        Route::post('fee-types', [FeeTypeController::class, 'store']);
        Route::put('fee-types/{feeType}', [FeeTypeController::class, 'update']);
        Route::patch('fee-types/{feeType}', [FeeTypeController::class, 'update']);
        Route::delete('fee-types/{feeType}', [FeeTypeController::class, 'destroy']);
        Route::patch('fee-types/{feeType}/toggle-active', [FeeTypeController::class, 'toggleActive']);
        // restore
        Route::post('fee-types/{id}/restore', [FeeTypeController::class, 'restore'])->name('fee-types.restore');

        // Route cho hành động toggle active
        Route::patch('fee-types/{fee_type}/toggle-active', [FeeTypeController::class, 'toggleActive'])->name('fee-types.toggle-active');
    });

    // ------------------------------------------------------------------------
    // INVOICE ROUTES
    // ------------------------------------------------------------------------

    // Special endpoints MUST come BEFORE resource routes to avoid conflicts

    // My invoices (Student/Parent)
    Route::get('my-invoices', [InvoiceController::class, 'myInvoices'])
        ->middleware('role:student|parent');

    // Overdue invoices (Admin/Principal/Accountant)
    Route::get('invoices/overdue', [InvoiceController::class, 'getOverdue'])
        ->middleware('role:admin|principal|accountant');

    // Statistics (Admin/Principal/Accountant)
    Route::get('invoices/statistics', [InvoiceController::class, 'statistics'])
        ->middleware('role:admin|principal|accountant');

    // Invoices by class (Admin/Principal/Accountant/Teacher)
    Route::get('invoices/class/{classId}', [InvoiceController::class, 'getByClass'])
        ->middleware('role:admin|principal|accountant|teacher');

    // Standard CRUD - View endpoints (all authenticated, authorization in service)
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::get('invoices/{id}', [InvoiceController::class, 'show']);

    // Create/Update/Delete (Admin/Principal/Accountant only)
    Route::middleware('role:admin|principal|accountant')->group(function () {
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::put('invoices/{id}', [InvoiceController::class, 'update']);
        Route::patch('invoices/{id}', [InvoiceController::class, 'update']);
        Route::delete('invoices/{id}', [InvoiceController::class, 'destroy']);
    });

    // ------------------------------------------------------------------------
    // PAYMENT ROUTES
    // ------------------------------------------------------------------------

    // Statistics (Admin/Principal/Accountant)
    Route::get('payments/statistics', [PaymentController::class, 'statistics'])
        ->middleware('role:admin|principal|accountant');

    // Payments by invoice (all authenticated, authorization in service)
    Route::get('invoices/{invoiceId}/payments', [PaymentController::class, 'getByInvoice']);

    // List payments (Admin/Principal/Accountant)
    Route::get('payments', [PaymentController::class, 'index'])
        ->middleware('role:admin|principal|accountant');

    // Show payment (all authenticated, authorization in service)
    Route::get('payments/{payment}', [PaymentController::class, 'show']);

    // Create payment (Admin/Principal/Accountant/Parent)
    Route::post('payments', [PaymentController::class, 'store'])
        ->middleware('role:admin|principal|accountant|parent');

    // Delete payment (Admin/Accountant only)
    Route::delete('payments/{id}', [PaymentController::class, 'destroy'])
        ->middleware('role:admin|accountant');

    // ------------------------------------------------------------------------
    // DISCIPLINE ROUTES
    // ------------------------------------------------------------------------
    Route::middleware('permission:record discipline')->group(function () {
        Route::apiResource('disciplines', DisciplineController::class);
    });

    // ------------------------------------------------------------------------
    // LIBRARY ROUTES
    // ------------------------------------------------------------------------
    Route::middleware('role_or_permission:admin|manage library')->group(function () {
        Route::apiResource('library-books', LibraryBookController::class);
        Route::apiResource('library-transactions', LibraryTransactionController::class);
    });

    // ------------------------------------------------------------------------
    // STUDENT & PARENT ROUTES
    // ------------------------------------------------------------------------
    Route::middleware('role:parent')->group(function () {
        Route::get('my-children', [StudentController::class, 'myChildren']);
    });

    // ------------------------------------------------------------------------
    // EVENT ROUTES
    // ------------------------------------------------------------------------
    Route::get('events', [EventController::class, 'index']);
    Route::get('events/{event}', [EventController::class, 'show']);
    Route::post('events/{event}/register', [EventController::class, 'register']);

    Route::middleware('role_or_permission:admin|manage events')->group(function () {
        Route::post('events', [EventController::class, 'store']);
        Route::put('events/{event}', [EventController::class, 'update']);
        Route::delete('events/{event}', [EventController::class, 'destroy']);
    });

    // ------------------------------------------------------------------------
    // ATTENDANCE ROUTES
    // ------------------------------------------------------------------------
    Route::middleware('role:teacher|admin')->group(function () {
        Route::post('attendances', [AttendanceController::class, 'store']);
        Route::put('attendances/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy']);
    });

    Route::middleware('role:student|parent|teacher|admin')->group(function () {
        Route::get('attendances', [AttendanceController::class, 'index']);
        Route::get('attendances/{attendance}', [AttendanceController::class, 'show']);
        Route::get('attendances/student/{student}', [AttendanceController::class, 'byStudent']);
    });

    // ------------------------------------------------------------------------
    // FINANCIAL REPORTS (Legacy route - if needed)
    // ------------------------------------------------------------------------
    Route::middleware('role_or_permission:admin|manage finances')->group(function () {
        Route::get('financial-reports', [PaymentController::class, 'reports']);
    });
});
