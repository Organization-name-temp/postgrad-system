<?php

use Illuminate\Support\Facades\Route;
use Modules\AcademicStanding\Internal\Http\Controllers\CreditHourSummaryController;
use Modules\AcademicStanding\Internal\Http\Controllers\GraduationReadinessController;
use Modules\AcademicStanding\Internal\Http\Controllers\StandingController;

Route::middleware(['auth:sanctum', 'can:standing.monitor'])->group(function () {
    Route::get('/academic-standing/me/credit-hours', [CreditHourSummaryController::class, 'mine']);
    Route::get('/academic-standing/cards/credit-hours', [CreditHourSummaryController::class, 'card'])->middleware('role:R02');
    Route::get('/academic-standing/students/{student_id}/credit-hours', [CreditHourSummaryController::class, 'show'])->whereNumber('student_id');
    Route::get('/academic-standing/me', [StandingController::class, 'mine']);
    Route::get('/academic-standing/cards/standing', [StandingController::class, 'card'])->middleware('role:R02');
    Route::get('/academic-standing/me/graduation-readiness', [GraduationReadinessController::class, 'mine']);
    Route::get('/academic-standing/students/{student_id}/graduation-readiness', [GraduationReadinessController::class, 'show'])->whereNumber('student_id');
    Route::get('/academic-standing/students/{student_id}', [StandingController::class, 'show'])->whereNumber('student_id');
});
