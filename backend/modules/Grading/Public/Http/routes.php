<?php

use Illuminate\Support\Facades\Route;
use Modules\Grading\Internal\Http\Controllers\GradeSheetController;

/*
| Grading routes, mounted under /api/v1 by GradingServiceProvider.
|
| Mark entry is gated on `grades.enter` — the §3.2 permission Instructors and Student
| Affairs hold — and the §3.2 matrix is untouched: no new permission and no new role
| mapping (ADR-0023). The permission alone is not authority over a *particular* Sheet,
| though: an Instructor's authority comes from the Offering assignment, which the
| controller checks itself (the Phase-2 interviewer / Phase-3 attendance precedent for
| authorising by assignment). Student Affairs' central-entry path onto another user's
| Sheet, and the ADR-0015 administrative override, arrive in their own tickets; until
| then holding the permission without the assignment is a 403.
|
| Signing and Department-Head review (`grades.finalize`) are ticket 03; nothing here
| signs or reviews.
|
| `Grading\Public` ships empty beyond these route bindings: the routes live in Public but
| point at the Internal controller (a same-module Public → Internal reference, the Calendar
| precedent). Id params are constrained to integers, and the nested ones are snake_case so
| Scribe/Orval stay deterministic (the [[scribe-urlparam-determinism]] rule).
*/

Route::middleware(['auth:sanctum', 'can:grades.enter'])->group(function () {
    // The acting Instructor's own Offerings — the entry point to each one's Grade Sheet.
    Route::get('/grading/offerings', [GradeSheetController::class, 'offerings']);

    // Open one Offering's Grade Sheet, creating the Initial round lazily on first open
    // (ADR-0023). {offering_id} is Enrollment-owned, so it is a plain id resolved through
    // the Enrollment\Public facade, never model-bound.
    Route::get('/grading/offerings/{offering_id}/sheet', [GradeSheetController::class, 'sheet'])
        ->whereNumber('offering_id');

    // Save one line's mark — per line, never as a batch (the Phase-2 per-step-save
    // precedent), so a long roster survives a closed tab. {grade_sheet} is Grading's own
    // model and is bound; {enrollment_id} is Enrollment-owned and is a plain id.
    Route::put('/grading/sheets/{grade_sheet}/lines/{enrollment_id}', [GradeSheetController::class, 'saveLine'])
        ->whereNumber('grade_sheet')
        ->whereNumber('enrollment_id');
});
