<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Internal\Http\Controllers\FacultyController;

/*
| Catalog routes, mounted under /api/v1 by CatalogServiceProvider. Reading
| requires catalog.view (held by every academic role); mutations require
| catalog.manage (Department Head / Dean / admin). All require an authenticated
| Sanctum session — the server is the sole authority (ADR-0008/0010).
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/faculties', [FacultyController::class, 'index'])->middleware('can:catalog.view');
    Route::get('/faculties/{faculty}', [FacultyController::class, 'show'])->middleware('can:catalog.view');

    Route::post('/faculties', [FacultyController::class, 'store'])->middleware('can:catalog.manage');
    Route::patch('/faculties/{faculty}', [FacultyController::class, 'update'])->middleware('can:catalog.manage');
    Route::post('/faculties/{faculty}/activate', [FacultyController::class, 'activate'])->middleware('can:catalog.manage');
    Route::post('/faculties/{faculty}/deactivate', [FacultyController::class, 'deactivate'])->middleware('can:catalog.manage');
});
