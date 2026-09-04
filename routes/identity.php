<?php

use App\Domains\Identity\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// ── T1.2 public auth ──
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password-reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password-reset');
    Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:6,1');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:refresh');
});

// ── T1.2 authenticated (JWT) ──
Route::middleware('auth.jwt')->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout/all', [AuthController::class, 'logoutAll']);
});

// Optional extended auth routes — only registered if controllers exist
if (class_exists(\App\Domains\Identity\Http\Controllers\Auth\EmailController::class)) {
    Route::middleware('auth.jwt')->prefix('auth')->group(function () {
        Route::post('email/verify/send', [\App\Domains\Identity\Http\Controllers\Auth\EmailController::class, 'send']);
        Route::put('password/change', [\App\Domains\Identity\Http\Controllers\Auth\PasswordController::class, 'change']);
        Route::apiResource('sessions', \App\Domains\Identity\Http\Controllers\Auth\SessionController::class)->only(['index', 'destroy']);
        Route::prefix('2fa')->group(function () {
            Route::post('totp/setup', [\App\Domains\Identity\Http\Controllers\Auth\TwoFactorController::class, 'setup']);
            Route::post('totp/enable', [\App\Domains\Identity\Http\Controllers\Auth\TwoFactorController::class, 'enable']);
            Route::post('totp/disable', [\App\Domains\Identity\Http\Controllers\Auth\TwoFactorController::class, 'disable']);
            Route::get('backup-codes', [\App\Domains\Identity\Http\Controllers\Auth\TwoFactorController::class, 'backupCodes']);
            Route::post('backup-codes/regenerate', [\App\Domains\Identity\Http\Controllers\Auth\TwoFactorController::class, 'regenerateBackupCodes']);
        });
    });
}

// Admin RBAC — disabled in T1.2 smoke env if RoleService missing
if (class_exists(\App\Domains\Identity\Services\RoleService::class)) {
    Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
        Route::get('admin/roles', [\App\Domains\Identity\Http\Controllers\RoleController::class, 'index']);
        Route::post('admin/roles', [\App\Domains\Identity\Http\Controllers\RoleController::class, 'store']);
        Route::get('admin/roles/{id}', [\App\Domains\Identity\Http\Controllers\RoleController::class, 'show']);
        Route::put('admin/roles/{id}', [\App\Domains\Identity\Http\Controllers\RoleController::class, 'update']);
        Route::delete('admin/roles/{id}', [\App\Domains\Identity\Http\Controllers\RoleController::class, 'destroy']);
        Route::get('permissions', [\App\Domains\Identity\Http\Controllers\PermissionController::class, 'index']);
        Route::get('permissions/{id}', [\App\Domains\Identity\Http\Controllers\PermissionController::class, 'show']);
        Route::post('admin/permissions', [\App\Domains\Identity\Http\Controllers\PermissionController::class, 'store']);
        Route::put('admin/permissions/{id}', [\App\Domains\Identity\Http\Controllers\PermissionController::class, 'update']);
        Route::delete('admin/permissions/{id}', [\App\Domains\Identity\Http\Controllers\PermissionController::class, 'destroy']);
    });
}
