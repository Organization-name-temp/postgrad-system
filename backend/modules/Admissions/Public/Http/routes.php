<?php

use Illuminate\Support\Facades\Route;
use Modules\Admissions\Internal\Http\Controllers\AdmissionCycleController;

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

Route::middleware(['auth:sanctum', 'can:calendar.manage'])->group(function () {
    Route::get('/admission-cycles', [AdmissionCycleController::class, 'index']);
    Route::post('/admission-cycles', [AdmissionCycleController::class, 'store']);
    Route::get('/admission-cycles/{admission_cycle}', [AdmissionCycleController::class, 'show'])->whereNumber('admission_cycle');
    Route::patch('/admission-cycles/{admission_cycle}', [AdmissionCycleController::class, 'update'])->whereNumber('admission_cycle');
    Route::post('/admission-cycles/{admission_cycle}/activate', [AdmissionCycleController::class, 'activate'])->whereNumber('admission_cycle');
    Route::post('/admission-cycles/{admission_cycle}/deactivate', [AdmissionCycleController::class, 'deactivate'])->whereNumber('admission_cycle');
});
