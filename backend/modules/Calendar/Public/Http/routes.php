<?php

use Illuminate\Support\Facades\Route;
use Modules\Calendar\Internal\Http\Controllers\SemesterController;

/*
| Calendar routes, mounted under /api/v1 by CalendarServiceProvider.
|
| Reading the calendar is open to any authenticated user who needs it (a student
| must be able to see the term and its dates). Creating and editing a Semester
| requires calendar.manage (Student Affairs / Dean; SysAdmin inherits it) — the
| same guard AdmissionCycle uses. Id params are constrained to integers.
|
| There is deliberately no delete route: a Semester is edited in place, never
| hard-deleted (ADR-0018), so a DELETE resolves to 405 Method Not Allowed.
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/semesters', [SemesterController::class, 'index']);
    Route::get('/semesters/{semester}', [SemesterController::class, 'show'])->whereNumber('semester');
});

Route::middleware(['auth:sanctum', 'can:calendar.manage'])->group(function () {
    Route::post('/semesters', [SemesterController::class, 'store']);
    Route::patch('/semesters/{semester}', [SemesterController::class, 'update'])->whereNumber('semester');
});
