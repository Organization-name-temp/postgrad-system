<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Internal\Http\Controllers\LoginController;
use Modules\Identity\Internal\Http\Controllers\LogoutController;
use Modules\Identity\Internal\Http\Controllers\MeController;
use Modules\Identity\Public\Http\HealthController;

/*
| Identity & Access routes, mounted under /api/v1 by IdentityServiceProvider.
*/

// Tracer endpoint proving the module route pattern (not Identity domain).
Route::get('/health', HealthController::class);

// Authentication (Sanctum SPA cookie session).
Route::post('/login', LoginController::class);
Route::post('/logout', LogoutController::class)->middleware('auth:sanctum');
Route::get('/me', MeController::class)->middleware('auth:sanctum');
