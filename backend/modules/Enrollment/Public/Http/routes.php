<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollment\Internal\Http\Controllers\OfferingController;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationController;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationReviewController;

/*
| Enrollment routes, mounted under /api/v1 by EnrollmentServiceProvider.
|
| Course Offering management — "what runs this semester" — is entirely under
| calendar.manage (Student Affairs / Dean; SysAdmin inherits): the same guard the
| Semester itself uses (ADR-0017 — catalog.manage is what the university *can* teach;
| calendar.manage is what actually *runs* this term). The student registration surface
| is gated on courses.register_self (the acting Student, R02); the advisor/instructor
| read surfaces arrive in later Phase-3 tickets under their own gates.
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

Route::middleware(['auth:sanctum', 'can:courses.register_self'])->group(function () {
    // The acting Student's eligible-course list for the currently-open Semester (ticket 04).
    Route::get('/registration/eligible-courses', [RegistrationController::class, 'eligibleCourses']);

    // The Student's cart submission → a Pending RegistrationRequest, and the request view
    // (ticket 05). Both act on the acting Student and the currently-open Semester, so
    // neither carries a model-bound route parameter.
    Route::post('/registration/requests', [RegistrationController::class, 'submit']);
    Route::get('/registration/requests', [RegistrationController::class, 'requests']);

    // The Student's own schedule — their minted Enrollments grouped by Semester (ticket 06).
    Route::get('/registration/schedule', [RegistrationController::class, 'schedule']);
});

Route::middleware(['auth:sanctum', 'can:registration.approve'])->group(function () {
    // The Advisor's review of their advisees' Pending requests (ticket 06): a live-scoped
    // queue, line-item review of one request, and the decision that mints Enrollments.
    // Student Affairs (and administrators) review unscoped (ScopesToAdvisees). The single
    // request is model-bound as {registration} → RegistrationRequest.
    Route::get('/registration/queue', [RegistrationReviewController::class, 'queue']);
    Route::get('/registration/requests/{registration}', [RegistrationReviewController::class, 'show'])->whereNumber('registration');
    Route::post('/registration/requests/{registration}/decision', [RegistrationReviewController::class, 'decide'])->whereNumber('registration');
});
