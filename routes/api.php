<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DisciplineTypeController;
use App\Http\Controllers\FeeTypeController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\UserRoleController;
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
use App\Http\Controllers\ConductScoreController;
use App\Http\Controllers\DashBoardController;
use App\Http\Controllers\RoleController;

Route::get('/test', [App\Http\Controllers\Api\TestController::class, 'index']);

Route::get('hello' , function(){
    return response()->json([
        'message' => 'hello'
    ]);
});
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

// ============================================================================
// ROLE AND PERMISSION ROUTES ( Authentication Required)
// ============================================================================

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
 // --- BẮT ĐẦU PHẦN THAY THẾ ---
    
    // THAY THẾ CHO: Route::apiResource('admin/roles', RoleController::class);

    // 1. Lấy danh sách roles
    Route::get('admin/roles', [RoleController::class, 'index']);

    // 2. Tạo role mới
    Route::post('admin/roles', [RoleController::class, 'store']);

    // 3. Lấy chi tiết một role
    Route::get('admin/roles/{id}', [RoleController::class, 'show']);

    // 4. Cập nhật một role (ĐÂY LÀ ROUTE BẠN ĐANG CẦN)
    Route::put('admin/roles/{id}', [RoleController::class, 'update']);

    // 5. Xóa một role
    Route::delete('admin/roles/{id}', [RoleController::class, 'destroy']);

    // --- KẾT THÚC PHẦN THAY THẾ ---::delete('admin/roles/{id}', [RoleController::class, 'destroy']);

    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('permissions/{id}', [PermissionController::class, 'show']);
    Route::post('admin/permissions', [PermissionController::class, 'store']);
    Route::put('admin/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('admin/permissions/{id}', [PermissionController::class, 'destroy']);
    
    
    // Lấy permissions của role
    Route::get('admin/roles/{id}/permissions', [RoleController::class, 'getRolePermissions']);
    
    Route::post('admin/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('admin/roles/{role}/permissions/{permission}', [RoleController::class, 'removePermission']);

    // Lấy roles và permissions của user
    Route::get('admin/users/{userId}/roles', [UserRoleController::class, 'getUserRoles']);
    Route::get('admin/users/{userId}/permissions', [UserRoleController::class, 'getUserPermissions']);

    // Gán/Xóa roles cho user
    Route::post('admin/users/{userId}/roles', [UserRoleController::class, 'assignRoles']);
    Route::delete('admin/users/{userId}/roles/{roleName}', [UserRoleController::class, 'removeRole']);

    // Gán/Xóa permissions trực tiếp cho user
    Route::post('admin/users/{userId}/permissions', [UserRoleController::class, 'assignPermissions']);
    Route::delete('admin/users/{user}/permissions/{permission}', [UserRoleController::class, 'removePermission']);
});


Route::middleware(['auth:sanctum', 'role:admin|principal'])->group(function () {
    Route::get('admin/users/{userId}/roles', [UserRoleController::class, 'getUserRoles']);
    Route::post('admin/users/{userId}/roles', [UserRoleController::class, 'assignRoles']);
    Route::delete('admin/users/{userId}/roles/{roleName}', [UserRoleController::class, 'removeRole']);
    Route::get('admin/users/{userId}/permissions', [UserRoleController::class, 'getUserPermissions']);
});

// ============================================================================
// QR CODE
// ============================================================================

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
        Route::get('/', [ProfileController::class, 'index']);
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

        // create user by admin
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

    // ⚠️ IMPORTANT: Special endpoints MUST come BEFORE resource routes to avoid conflicts

    // My invoices (Student/Parent/Teacher) - FIX: Use array syntax for multiple roles
    Route::get('my-invoices', [InvoiceController::class, 'myInvoices'])
        ->middleware(['role:student|parent|teacher']);

    // OR better: Remove middleware and handle in controller
    Route::get('my-invoices', [InvoiceController::class, 'myInvoices']);

    // Overdue invoices (MUST be before {id})
    Route::get('invoices/overdue', [InvoiceController::class, 'getOverdue'])
        ->middleware(['role:admin|principal|accountant']);

    // Statistics (MUST be before {id})
    Route::get('invoices/statistics', [InvoiceController::class, 'statistics'])
        ->middleware(['role:admin|principal|accountant']);

    // Bulk create (MUST be before {id})
    Route::post('invoices/bulk-create', [InvoiceController::class, 'bulkCreate'])
        ->middleware(['role:admin|principal|accountant']);

    // Update overdue (MUST be before {id})
    Route::post('invoices/update-overdue', [InvoiceController::class, 'updateOverdueStatuses'])
        ->middleware(['role:admin|principal|accountant']);

    // Class invoices (MUST be before {id})
    Route::get('invoices/class/{classId}', [InvoiceController::class, 'getByClass'])
        ->middleware(['role:admin|principal|accountant|teacher']);

    // List all invoices
    Route::get('invoices', [InvoiceController::class, 'index']);

    // Payments for invoice (MUST be before invoices/{id})
    Route::get('invoices/{invoiceId}/payments', [PaymentController::class, 'getByInvoice']);

    // Show single invoice (MUST be LAST among GET routes)
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])
        ->where('invoice', '[0-9]+');


    // CRUD operations (Admin/Principal/Accountant only)
    Route::middleware(['role:admin|principal|accountant'])->group(function () {
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
    // =================================================================
    // == DISCIPLINE ROUTES (Phân hệ Kỷ luật & Hạnh kiểm)
    // =================================================================

    // --- DISCIPLINE MANAGEMENT (Quản lý các vụ việc vi phạm) ---
    Route::prefix('disciplines')->name('disciplines.')->group(function () {

        // **Xem (Read)**
        Route::get('/', [DisciplineController::class, 'index'])->name('index'); // Ai cũng có thể gọi, Service sẽ lọc
        Route::get('/my', [DisciplineController::class, 'my'])->middleware('role:student|parent')->name('my');
        Route::get('/class/{classId}', [DisciplineController::class, 'byClass'])->middleware('role:admin|principal|teacher')->name('by-class');
        Route::get('/statistics', [DisciplineController::class, 'statistics'])->middleware('role:admin|principal')->name('statistics');
        Route::get('/student/{studentId}', [DisciplineController::class, 'byStudent'])->middleware('role:admin|principal|teacher')->name('by-student');
        Route::get('/{discipline}', [DisciplineController::class, 'show'])->name('show'); // Policy sẽ kiểm tra quyền xem chi tiết

        // **Thống kê & Xuất file (Admin/Principal)**
        Route::get('/statistics', [DisciplineController::class, 'statistics'])->middleware('role:admin|principal')->name('statistics');

        Route::get('/export', [DisciplineController::class, 'export'])->middleware('role:admin|principal')->name('export');

        // **Tạo (Create)** - Yêu cầu quyền 'record discipline'
        Route::post('/', [DisciplineController::class, 'store'])->middleware('permission:record discipline')->name('store');

        // **Cập nhật (Update)** - Logic quyền phức tạp, xử lý trong Policy
        Route::put('/{discipline}', [DisciplineController::class, 'update'])->name('update');

        // **Xóa (Delete)** - Chỉ Admin/Principal
        Route::delete('/{discipline}', [DisciplineController::class, 'destroy'])->middleware('role:admin|principal')->name('destroy');

        // **Hành động xử lý (Approve, Reject, Appeal)**
        Route::post('/{discipline}/approve', [DisciplineController::class, 'approve'])->middleware('role:admin|principal')->name('approve');
        Route::post('/{discipline}/reject', [DisciplineController::class, 'reject'])->middleware('role:admin|principal')->name('reject');
        Route::post('/{discipline}/appeal', [DisciplineController::class, 'appeal'])->middleware('role:student|parent')->name('appeal');
    });

    // --- DISCIPLINE TYPES MANAGEMENT (Quản lý các loại vi phạm) ---
    // Chỉ Admin/Principal mới có toàn quyền
    Route::prefix('discipline-types')->name('discipline-types.')->middleware('role:admin|principal')->group(function () {
        Route::get('/', [DisciplineTypeController::class, 'index'])->withoutMiddleware('role:admin|principal'); // Cho phép mọi người xem
        Route::get('/{disciplineType}', [DisciplineTypeController::class, 'show'])->withoutMiddleware('role:admin|principal'); // Cho phép mọi người xem

        Route::post('/', [DisciplineTypeController::class, 'store'])->name('store');
        Route::put('/{disciplineType}', [DisciplineTypeController::class, 'update'])->name('update');
        Route::delete('/{disciplineType}', [DisciplineTypeController::class, 'destroy'])->name('destroy');
    });

    // --- CONDUCT SCORES MANAGEMENT (Quản lý điểm hạnh kiểm) ---
    Route::prefix('conduct-scores')->name('conduct-scores.')->group(function () {

        // **Xem (Read)**
        Route::get('/my', [ConductScoreController::class, 'my'])->middleware('role:student|parent')->name('my');
        Route::get('/class/{classId}', [ConductScoreController::class, 'byClass'])->middleware('role:admin|principal|teacher')->name('by-class');
        Route::get('/student/{studentId}', [ConductScoreController::class, 'byStudent'])->middleware('role:admin|principal|teacher')->name('by-student');

        Route::post('/', [ConductScoreController::class, 'store'])->middleware('role:admin|principal|teacher')->name('store');

        // **Cập nhật & Phê duyệt**
        Route::put('/{conductScore}', [ConductScoreController::class, 'update'])->middleware('role:teacher|admin|principal')->name('update'); // GVCN nhập nhận xét
        Route::post('/{conductScore}/approve', [ConductScoreController::class, 'approve'])->middleware('role:admin|principal')->name('approve');

        // **Tính toán lại**
        Route::post('/recalculate', [ConductScoreController::class, 'recalculate'])->middleware('role:admin|principal')->name('recalculate');
    });


    // ------------------------------------------------------------------------
    // STUDENT & PARENT ROUTES
    // ------------------------------------------------------------------------
    Route::middleware('role:parent')->group(function () {
        Route::get('my-children', [StudentController::class, 'myChildren']);
    });

    // ------------------------------------------------------------------------
    // FINANCIAL REPORTS (Legacy route - if needed)
    // ------------------------------------------------------------------------
    Route::middleware('role_or_permission:admin|manage finances')->group(function () {
        Route::get('financial-reports', [PaymentController::class, 'reports']);
    });
});
