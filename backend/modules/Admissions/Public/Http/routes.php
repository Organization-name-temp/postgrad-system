<?php

use Illuminate\Support\Facades\Route;
use Modules\Admissions\Internal\Http\Controllers\AdmissionCycleController;
use Modules\Admissions\Internal\Http\Controllers\ApplicantInterviewController;
use Modules\Admissions\Internal\Http\Controllers\ApplicationController;
use Modules\Admissions\Internal\Http\Controllers\ApplicationScreeningController;
use Modules\Admissions\Internal\Http\Controllers\DeanReviewController;
use Modules\Admissions\Internal\Http\Controllers\DocumentController;
use Modules\Admissions\Internal\Http\Controllers\EmailVerificationController;
use Modules\Admissions\Internal\Http\Controllers\InterviewEvaluationController;
use Modules\Admissions\Internal\Http\Controllers\InterviewSchedulingController;
use Modules\Admissions\Internal\Http\Controllers\InterviewSlotController;
use Modules\Admissions\Internal\Http\Controllers\ProgramSelectionController;
use Modules\Admissions\Internal\Http\Controllers\RegistrationController;

/*
| Admissions routes, mounted under /api/v1 by AdmissionsServiceProvider.
|
| The open-cycle lookup is the first unauthenticated door into the system — it
| feeds the registration screen. It is declared before the model-bound routes and
| the id params are constrained to integers so "open" is never read as a Cycle id.
|
| Cycle management (list/create/edit/activate/deactivate) requires calendar.manage
| (Student Affairs / Dean); there is no separate "view cycles" permission. Whether
| a Cycle is open is derived, never stored (ADR-0012, ticket 01).
*/

// Public: currently-open cycles for the (unauthenticated) registration screen.
Route::get('/admission-cycles/open', [AdmissionCycleController::class, 'open']);

// Public: register and start an application. Unauthenticated and rate-limited
// (the `register` limiter, defined in AdmissionsServiceProvider); it establishes
// the applicant's session itself (ticket 03).
Route::post('/register', RegistrationController::class)->middleware('throttle:register');

// Public (signed, not session-authed): the emailed-style verification link. The
// signature is the credential — the link may be clicked from anywhere — so this
// sits outside the auth group, guarded by `signed` (bad/expired → 403). Declared
// before the model-bound applicant routes and constrained to an integer id.
Route::get('/applications/{application}/verify-email', [EmailVerificationController::class, 'verify'])
    ->middleware('signed')
    ->whereNumber('application')
    ->name('admissions.verify-email');

// Applicant-owned Step-1 wizard (ticket 04). Each acts on the signed-in applicant's
// own Application implicitly — no id in the path — so no ownership policy is needed
// beyond an authenticated session.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/application', [ApplicationController::class, 'show']);
    Route::patch('/application', [ApplicationController::class, 'update']);
    Route::post('/application/submit', [ApplicationController::class, 'submit']);
    // Resend is throttled per applicant to a few attempts per day (BR-72); the
    // `verify-resend` limiter is defined in AdmissionsServiceProvider.
    Route::post('/application/resend-verification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:verify-resend');

    // Documents (ticket 05). Upload and remove act on the signed-in applicant's own
    // Application; removal is bound to a document by its ULID (never a row id) and
    // 404s a document that is not theirs.
    Route::post('/application/documents', [DocumentController::class, 'store']);
    Route::delete('/application/documents/{document}', [DocumentController::class, 'destroy'])->whereUlid('document');

    // Program selection + finalize (ticket 06). Selection validates the chosen
    // Catalog Program(s) through the Catalog\Public facade (ADR-0012); finalize
    // gates on a complete application and moves EmailVerified → UnderSAReview.
    Route::patch('/application/programs', [ProgramSelectionController::class, 'update']);
    Route::post('/application/finalize', [ApplicationController::class, 'finalize']);

    // Resubmit after revision (ticket 07): ReturnedForRevision → UnderSAReview, once
    // the applicant has addressed the reviewer's instructions.
    Route::post('/application/resubmit', [ApplicationController::class, 'resubmit']);

    // Applicant interview (ticket 09): the applicant views their booked interview,
    // sees the open slots they could move to, and either confirms it or reschedules
    // it once. These act on the signed-in applicant's own interview implicitly (no id
    // in the path), so an authenticated session is the only gate. `slots` is declared
    // before the bare `interview` GET so it is not read as a sub-resource of it.
    Route::get('/application/interview/slots', [ApplicantInterviewController::class, 'openSlots']);
    Route::get('/application/interview', [ApplicantInterviewController::class, 'show']);
    Route::post('/application/interview/confirm', [ApplicantInterviewController::class, 'confirm']);
    Route::post('/application/interview/reschedule', [ApplicantInterviewController::class, 'reschedule']);
});

// Interviewer evaluation (ticket 09). Sanctum-authed but **not** permission-gated: an
// interviewer holds no reviewer permission, so authorization is row-level — the
// controller allows an action only for the interviewer the slot is assigned to
// (anyone else → 403). `assigned` is declared before the id-bound evaluation route.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/interviews/assigned', [InterviewEvaluationController::class, 'index']);
    Route::post('/interviews/{interview}/evaluation', [InterviewEvaluationController::class, 'store'])
        ->whereNumber('interview');
});

// Interview scheduling surface (ticket 09), gated on `interview.schedule` (Student
// Affairs / Dean): the slot pool CRUD + interviewer picker, booking an approved
// applicant into a slot, and marking an interview complete. The `interviewers` picker
// is declared before the model-bound slot routes and the id params are constrained to
// integers so it is never read as a slot id.
Route::middleware(['auth:sanctum', 'can:interview.schedule'])->group(function () {
    // Student Affairs' scheduling worklist: Dean-approved applicants not yet booked.
    // Declared before the model-bound `/applications/{application}` reviewer routes;
    // the literal segment cannot be read as an integer id (they are whereNumber-bound).
    Route::get('/applications/awaiting-interview', [InterviewSchedulingController::class, 'index']);

    Route::get('/interview-slots/interviewers', [InterviewSlotController::class, 'interviewers']);
    Route::get('/interview-slots', [InterviewSlotController::class, 'index']);
    Route::post('/interview-slots', [InterviewSlotController::class, 'store']);
    Route::patch('/interview-slots/{interview_slot}', [InterviewSlotController::class, 'update'])->whereNumber('interview_slot');
    Route::delete('/interview-slots/{interview_slot}', [InterviewSlotController::class, 'destroy'])->whereNumber('interview_slot');

    Route::get('/interviews', [InterviewSchedulingController::class, 'booked']);
    Route::post('/applications/{application}/interview', [InterviewSchedulingController::class, 'store'])->whereNumber('application');
    Route::post('/interviews/{interview}/complete', [InterviewSchedulingController::class, 'complete'])->whereNumber('interview');
});

// Status timeline (ticket 07). Sanctum-authed but not applicant-scoped: readable by
// the owning applicant *or* a reviewer holding `application.screen` (checked in the
// controller), anyone else → 403. Declared before the reviewer group so it is not
// swallowed by the `can:application.screen` gate the applicant does not hold.
Route::middleware('auth:sanctum')
    ->get('/applications/{application}/timeline', [ApplicationScreeningController::class, 'timeline'])
    ->whereNumber('application');

// Reviewer screening (ticket 07). The Student Affairs queue, application detail, and
// the screen action, all gated on `application.screen` (SA / Dean).
Route::middleware(['auth:sanctum', 'can:application.screen'])->group(function () {
    Route::get('/applications', [ApplicationScreeningController::class, 'index']);
    Route::get('/applications/{application}', [ApplicationScreeningController::class, 'show'])->whereNumber('application');
    Route::post('/applications/{application}/screen', [ApplicationScreeningController::class, 'screen'])->whereNumber('application');
});

// Dean review (ticket 08). The Dean's faculty-scoped queue and decision, gated on
// `application.screen`; the per-application faculty scope (out-of-faculty → 403) is
// enforced inside the controller via the Dean↔Faculty assignment mapping.
Route::middleware(['auth:sanctum', 'can:application.screen'])->group(function () {
    Route::get('/dean/applications', [DeanReviewController::class, 'index']);
    Route::post('/dean/applications/{application}/review', [DeanReviewController::class, 'review'])->whereNumber('application');
});

// Streaming download (ticket 05). Sanctum-authed but not applicant-scoped: served
// to the owning applicant *or* a reviewer holding `application.screen` (checked in
// the controller), anyone else → 403. The ULID is the only handle in the URL.
Route::middleware('auth:sanctum')
    ->get('/documents/{document}', [DocumentController::class, 'download'])
    ->whereUlid('document')
    ->name('admissions.documents.download');

Route::middleware(['auth:sanctum', 'can:calendar.manage'])->group(function () {
    Route::get('/admission-cycles', [AdmissionCycleController::class, 'index']);
    Route::post('/admission-cycles', [AdmissionCycleController::class, 'store']);
    Route::get('/admission-cycles/{admission_cycle}', [AdmissionCycleController::class, 'show'])->whereNumber('admission_cycle');
    Route::patch('/admission-cycles/{admission_cycle}', [AdmissionCycleController::class, 'update'])->whereNumber('admission_cycle');
    Route::post('/admission-cycles/{admission_cycle}/activate', [AdmissionCycleController::class, 'activate'])->whereNumber('admission_cycle');
    Route::post('/admission-cycles/{admission_cycle}/deactivate', [AdmissionCycleController::class, 'deactivate'])->whereNumber('admission_cycle');
});
