<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JwksController;

/*
|--------------------------------------------------------------------------
| API Routes (Gateway Health + JWKS)
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String()
    ]);
});

// JWKS — public, no auth required
Route::get('/jwks', [JwksController::class, 'index']);
Route::get('/auth/jwks', [JwksController::class, 'index']);
Route::get('/auth/public-key', [JwksController::class, 'publicKey']);

