<?php

use Illuminate\Support\Facades\Route;
use Modules\Catalog\Internal\Http\Controllers\CourseController;
use Modules\Catalog\Internal\Http\Controllers\CurriculumController;
use Modules\Catalog\Internal\Http\Controllers\DepartmentController;
use Modules\Catalog\Internal\Http\Controllers\FacultyController;
use Modules\Catalog\Internal\Http\Controllers\ProgramController;

/*
| Catalog routes, mounted under /api/v1 by CatalogServiceProvider. Reading
| requires catalog.view (held by every academic role); mutations require
| catalog.manage (Department Head / Dean / admin). All require an authenticated
| Sanctum session — the server is the sole authority (ADR-0008/0010).
*/

Route::middleware('auth:sanctum')->group(function () {
    // Faculties
    Route::get('/faculties', [FacultyController::class, 'index'])->middleware('can:catalog.view');
    Route::get('/faculties/{faculty}', [FacultyController::class, 'show'])->middleware('can:catalog.view');
    Route::post('/faculties', [FacultyController::class, 'store'])->middleware('can:catalog.manage');
    Route::patch('/faculties/{faculty}', [FacultyController::class, 'update'])->middleware('can:catalog.manage');
    Route::post('/faculties/{faculty}/activate', [FacultyController::class, 'activate'])->middleware('can:catalog.manage');
    Route::post('/faculties/{faculty}/deactivate', [FacultyController::class, 'deactivate'])->middleware('can:catalog.manage');

    // Departments
    Route::get('/departments', [DepartmentController::class, 'index'])->middleware('can:catalog.view');
    Route::get('/departments/{department}', [DepartmentController::class, 'show'])->middleware('can:catalog.view');
    Route::post('/departments', [DepartmentController::class, 'store'])->middleware('can:catalog.manage');
    Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->middleware('can:catalog.manage');
    Route::post('/departments/{department}/activate', [DepartmentController::class, 'activate'])->middleware('can:catalog.manage');
    Route::post('/departments/{department}/deactivate', [DepartmentController::class, 'deactivate'])->middleware('can:catalog.manage');

    // Programs
    Route::get('/programs', [ProgramController::class, 'index'])->middleware('can:catalog.view');
    Route::get('/programs/{program}', [ProgramController::class, 'show'])->middleware('can:catalog.view');
    Route::post('/programs', [ProgramController::class, 'store'])->middleware('can:catalog.manage');
    Route::patch('/programs/{program}', [ProgramController::class, 'update'])->middleware('can:catalog.manage');
    Route::post('/programs/{program}/activate', [ProgramController::class, 'activate'])->middleware('can:catalog.manage');
    Route::post('/programs/{program}/deactivate', [ProgramController::class, 'deactivate'])->middleware('can:catalog.manage');

    // Curriculum (a Program's Curriculum Entries — Courses placed into Categories)
    Route::get('/programs/{program}/curriculum', [CurriculumController::class, 'index'])->middleware('can:catalog.view');
    Route::post('/programs/{program}/curriculum', [CurriculumController::class, 'store'])->middleware('can:catalog.manage');
    Route::delete('/programs/{program}/curriculum/{curriculum_entry}', [CurriculumController::class, 'destroy'])->middleware('can:catalog.manage');

    // Courses
    Route::get('/courses', [CourseController::class, 'index'])->middleware('can:catalog.view');
    Route::get('/courses/{course}', [CourseController::class, 'show'])->middleware('can:catalog.view');
    Route::post('/courses', [CourseController::class, 'store'])->middleware('can:catalog.manage');
    Route::patch('/courses/{course}', [CourseController::class, 'update'])->middleware('can:catalog.manage');
    Route::post('/courses/{course}/activate', [CourseController::class, 'activate'])->middleware('can:catalog.manage');
    Route::post('/courses/{course}/deactivate', [CourseController::class, 'deactivate'])->middleware('can:catalog.manage');
});
