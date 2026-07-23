<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Internal\Http\Controllers\OfferingController;

/*
| Enrollment routes, mounted under /api/v1 by EnrollmentServiceProvider.
|
| Course Offering management — "what runs this semester" — is entirely under
| calendar.manage (Student Affairs / Dean; SysAdmin inherits): the same guard the
| Semester itself uses (ADR-0017 — catalog.manage is what the university *can* teach;
| calendar.manage is what actually *runs* this term). The eligible-list / roster read
| surfaces students and instructors use arrive in later Phase-3 tickets under their
| own gates.
|
| `Enrollment\Public` ships empty beyond these route bindings (ADR-0017): the routes
| live in Public but point at the Internal controller (a same-module Public → Internal
| reference, the Calendar precedent). Id params are constrained to integers.
*/

Route::middleware(['auth:sanctum', 'can:calendar.manage'])->group(function () {
    // The instructor picker — R04 users, the Phase-2 interviewer-list precedent.
    Route::get('/instructors', [OfferingController::class, 'instructors']);

    // Offerings scoped to a Semester (the per-semester management screen).
    Route::get('/semesters/{semester_id}/offerings', [OfferingController::class, 'index'])->whereNumber('semester_id');
    Route::post('/semesters/{semester_id}/offerings', [OfferingController::class, 'store'])->whereNumber('semester_id');

    // A single Offering by id.
    Route::get('/offerings/{offering}', [OfferingController::class, 'show'])->whereNumber('offering');
    Route::patch('/offerings/{offering}', [OfferingController::class, 'update'])->whereNumber('offering');
    Route::delete('/offerings/{offering}', [OfferingController::class, 'destroy'])->whereNumber('offering');
});
