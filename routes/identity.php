<?php

use App\Domains\Identity\Http\Controllers\Auth\AuthController;
use App\Domains\Identity\Http\Controllers\Auth\EmailController;
use App\Domains\Identity\Http\Controllers\Auth\PasswordController;
use App\Domains\Identity\Http\Controllers\Auth\TwoFactorController;
use App\Domains\Identity\Http\Controllers\Auth\SessionController;
use App\Domains\Identity\Http\Controllers\RoleController;
use App\Domains\Identity\Http\Controllers\PermissionController;
use App\Domains\Identity\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware('auth.jwt')->prefix('auth')->group(function () {
    Route::get('me', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('logout/all', [AuthController::class, 'logoutAll']);
    Route::post('email/verify/send', [EmailController::class, 'send']);
    Route::put('password/change', [PasswordController::class, 'change']);
    Route::apiResource('sessions', SessionController::class)->only(['index', 'destroy']);
    Route::prefix('2fa')->group(function () {
        Route::post('totp/setup', [TwoFactorController::class, 'setup']);
        Route::post('totp/enable', [TwoFactorController::class, 'enable']);
        Route::post('totp/disable', [TwoFactorController::class, 'disable']);
        Route::get('backup-codes', [TwoFactorController::class, 'backupCodes']);
        Route::post('backup-codes/regenerate', [TwoFactorController::class, 'regenerateBackupCodes']);
    });
});

Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
    Route::get('admin/roles', [RoleController::class, 'index']);
    Route::post('admin/roles', [RoleController::class, 'store']);
    Route::get('admin/roles/{id}', [RoleController::class, 'show']);
    Route::put('admin/roles/{id}', [RoleController::class, 'update']);
    Route::delete('admin/roles/{id}', [RoleController::class, 'destroy']);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('permissions/{id}', [PermissionController::class, 'show']);
    Route::post('admin/permissions', [PermissionController::class, 'store']);
    Route::put('admin/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('admin/permissions/{id}', [PermissionController::class, 'destroy']);
    Route::get('admin/roles/{id}/permissions', [RoleController::class, 'getRolePermissions']);
    Route::post('admin/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('admin/roles/{role}/permissions/{permission}', [RoleController::class, 'removePermission']);
    Route::get('admin/users/{userId}/roles', [UserRoleController::class, 'getUserRoles']);
    Route::get('admin/users/{userId}/permissions', [UserRoleController::class, 'getUserPermissions']);
    Route::post('admin/users/{userId}/roles', [UserRoleController::class, 'assign']);
    Route::delete('admin/users/{userId}/roles/{role}', [UserRoleController::class, 'revoke']);
});
