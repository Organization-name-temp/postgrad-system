<?php

use Illuminate\Support\Facades\Route;
use Modules\Records\Internal\Http\Controllers\FeeController;
use Modules\Records\Internal\Http\Controllers\RequestController;
use Modules\Records\Internal\Http\Controllers\TranscriptController;

Route::middleware(['auth:sanctum', 'can:transcript.view_unofficial'])->prefix('records')->group(function (): void {
    Route::get('/students', [TranscriptController::class, 'index']);
    Route::get('/students/export', [TranscriptController::class, 'export']);
    Route::get('/students/{student_id}/transcript', [TranscriptController::class, 'show'])->whereNumber('student_id');
});

Route::middleware('auth:sanctum')->prefix('records')->group(function (): void {
    Route::get('/service', [FeeController::class, 'availability'])->middleware('can:transcript.view_unofficial');
    Route::middleware('can:transcript.issue_official')->group(function (): void {
        Route::get('/fee', [FeeController::class, 'show']);
        Route::put('/fee', [FeeController::class, 'update']);
    });
});

Route::middleware(['auth:sanctum', 'can:transcript.view_unofficial'])->prefix('records/requests')->group(function (): void {
    Route::get('/', [RequestController::class, 'index']);
    Route::post('/', [RequestController::class, 'store'])->middleware('role:R02');
    Route::get('/{request_id}', [RequestController::class, 'show'])->whereNumber('request_id');
    Route::post('/{request_id}/review', [RequestController::class, 'review'])->whereNumber('request_id')->middleware('can:transcript.issue_official');
});
