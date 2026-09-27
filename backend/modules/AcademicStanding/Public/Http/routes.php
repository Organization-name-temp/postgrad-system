<?php

use Illuminate\Support\Facades\Route;
use Modules\AcademicStanding\Internal\Http\Controllers\CreditHourSummaryController;
use Modules\AcademicStanding\Internal\Http\Controllers\GraduationReadinessController;
use Modules\AcademicStanding\Internal\Http\Controllers\StandingController;
use Modules\AcademicStanding\Internal\Http\Controllers\StandingReportController;
use Modules\AcademicStanding\Internal\Http\Controllers\TerminationCaseController;

Route::middleware(['auth:sanctum', 'can:standing.monitor'])->group(function () {
    Route::get('/academic-standing/reports/standing', [StandingReportController::class, 'summary']);
    Route::get('/academic-standing/me/credit-hours', [CreditHourSummaryController::class, 'mine']);
    Route::get('/academic-standing/cards/credit-hours', [CreditHourSummaryController::class, 'card'])->middleware('role:R02');
    Route::get('/academic-standing/students/{student_id}/credit-hours', [CreditHourSummaryController::class, 'show'])->whereNumber('student_id');
    Route::get('/academic-standing/me', [StandingController::class, 'mine']);
    Route::get('/academic-standing/cards/standing', [StandingController::class, 'card'])->middleware('role:R02');
    Route::get('/academic-standing/me/graduation-readiness', [GraduationReadinessController::class, 'mine']);
    Route::get('/academic-standing/students/{student_id}/graduation-readiness', [GraduationReadinessController::class, 'show'])->whereNumber('student_id');
    Route::get('/academic-standing/students/{student_id}', [StandingController::class, 'show'])->whereNumber('student_id');
});

Route::get('/termination-cases/mine', [TerminationCaseController::class, 'mine'])->middleware(['auth:sanctum', 'role:R02']);
Route::middleware(['auth:sanctum', 'can:termination.recommend'])->group(function () {
    Route::get('/termination-cases/advisor', [TerminationCaseController::class, 'advisorQueue'])->middleware('role:R03');
    Route::get('/termination-cases/advisor/export', [TerminationCaseController::class, 'advisorExport'])->middleware('role:R03');
    Route::post('/termination-cases/{case}/advisor-review', [TerminationCaseController::class, 'advisorReview'])->whereNumber('case')->middleware('role:R03');
    Route::get('/termination-cases/department-head', [TerminationCaseController::class, 'departmentQueue'])->middleware('role:R06');
    Route::get('/termination-cases/department-head/export', [TerminationCaseController::class, 'departmentExport'])->middleware('role:R06');
    Route::post('/termination-cases/{case}/department-review', [TerminationCaseController::class, 'departmentReview'])->whereNumber('case')->middleware('role:R06');
});
Route::middleware(['auth:sanctum', 'can:termination.approve', 'role:R08'])->group(function () {
    Route::get('/termination-cases/council', [TerminationCaseController::class, 'councilQueue']);
    Route::get('/termination-cases/council/export', [TerminationCaseController::class, 'councilExport']);
    Route::post('/termination-cases/{case}/council-decision', [TerminationCaseController::class, 'councilDecision'])->whereNumber('case');
});
