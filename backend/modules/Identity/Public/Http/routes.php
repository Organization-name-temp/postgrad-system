<?php

use Illuminate\Support\Facades\Route;
use Modules\Identity\Internal\Http\Controllers\DepartmentHeadAssignmentController;
use Modules\Identity\Internal\Http\Controllers\FacultyDeanAssignmentController;
use Modules\Identity\Internal\Http\Controllers\LoginController;
use Modules\Identity\Internal\Http\Controllers\LogoutController;
use Modules\Identity\Internal\Http\Controllers\MeController;
use Modules\Identity\Internal\Http\Controllers\UpdateCurrentUserController;
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
Route::patch('/me', UpdateCurrentUserController::class)->middleware('auth:sanctum');

// Administration (ticket 08). The first role-gated surface: the `admin` middleware
// admits only R09/R10 (no new permission, ADR-0008). An administrator maintains the
// Dean↔Faculty routing the Admissions Dean queue reads.
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/deans', [FacultyDeanAssignmentController::class, 'deans']);
    Route::get('/faculty-dean-assignments', [FacultyDeanAssignmentController::class, 'index']);
    Route::post('/faculty-dean-assignments', [FacultyDeanAssignmentController::class, 'store']);
    Route::delete('/faculty-dean-assignments/{assignment}', [FacultyDeanAssignmentController::class, 'destroy'])
        ->whereNumber('assignment');

    // Department-Head↔Department routing (phase 4, ticket 02), beside the Dean map and
    // behind the same role gate. Grading reads the result to route a signed Grade Sheet
    // to its reviewer; a NULL Department is the university-wide Preparatory scope.
    Route::get('/department-heads', [DepartmentHeadAssignmentController::class, 'heads']);
    Route::get('/department-head-assignments', [DepartmentHeadAssignmentController::class, 'index']);
    Route::post('/department-head-assignments', [DepartmentHeadAssignmentController::class, 'store']);
    Route::delete('/department-head-assignments/{assignment}', [DepartmentHeadAssignmentController::class, 'destroy'])
        ->whereNumber('assignment');
});
