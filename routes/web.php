<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JwksController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return response()->json([
        'service' => 'EduConnect API Gateway',
        'status' => 'active',
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Well-known JWKS endpoints — public, no auth (RFC 7517 / OIDC Discovery)
Route::get('/.well-known/jwks.json', [JwksController::class, 'index']);
Route::get('/.well-known/openid-configuration', [JwksController::class, 'openIdConfiguration']);
