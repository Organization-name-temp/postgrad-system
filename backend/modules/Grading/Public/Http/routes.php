<?php

use Illuminate\Support\Facades\Route;
use Modules\Grading\Internal\Http\Controllers\DepartmentHeadReviewController;
use Modules\Grading\Internal\Http\Controllers\GradeSheetController;
use Modules\Grading\Internal\Http\Controllers\MyGradesController;

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
| Signing and Department-Head review (ticket 03) share `grades.finalize` — held by both
| Instructor and Department Head (§3.2) — with authority distinguished by assignment: the
| Offering for a signature, the Department (via ScopesToDepartmentHead) for a review.
|
| `Grading\Public` ships empty beyond these route bindings: the routes live in Public but
| point at the Internal controller (a same-module Public → Internal reference, the Calendar
| precedent). Id params are constrained to integers, and the nested ones are snake_case so
| Scribe/Orval stay deterministic (the [[scribe-urlparam-determinism]] rule).
|
| My Grades (ticket 04) is gated on `grades.view_own` — Student only (§3.2) — and carries no
| id param: it always reads the acting user's own Student anchor, so another Student's record
| is unreachable from it (the scoped Advisor/Student-Affairs read is ticket 08's own surface).
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

Route::middleware(['auth:sanctum', 'can:grades.finalize'])->group(function () {
    // The Instructor's signature — Draft/Returned -> Submitted (ADR-0023). Authority is the
    // Offering assignment, checked in the controller.
    Route::post('/grading/sheets/{grade_sheet}/sign', [GradeSheetController::class, 'sign'])
        ->whereNumber('grade_sheet');

    // The Department Head's review queue and one Sheet's review screen. Authority is the
    // Department assignment (ScopesToDepartmentHead), checked in the controller — a Head
    // assigned university-wide reaches the department-less Preparatory Sheets, and no
    // Department-scoped Head does.
    Route::get('/grading/department-head/sheets', [DepartmentHeadReviewController::class, 'queue']);
    Route::get('/grading/department-head/sheets/{grade_sheet}', [DepartmentHeadReviewController::class, 'show'])
        ->whereNumber('grade_sheet');
    Route::post('/grading/department-head/sheets/{grade_sheet}/finalize', [DepartmentHeadReviewController::class, 'finalize'])
        ->whereNumber('grade_sheet');
    Route::post('/grading/department-head/sheets/{grade_sheet}/return', [DepartmentHeadReviewController::class, 'returnSheet'])
        ->whereNumber('grade_sheet');
});

Route::middleware(['auth:sanctum', 'can:grades.view_own'])->group(function () {
    // The acting Student's own academic record — Semester GPAs and the CGPA prominently
    // (ticket 04). No id param: always the acting user's own Student anchor.
    Route::get('/grading/my-grades', [MyGradesController::class, 'index']);
});
