<?php

use Illuminate\Support\Facades\Route;
use Modules\Records\Internal\Http\Controllers\CancellationController;
use Modules\Records\Internal\Http\Controllers\FeeController;
use Modules\Records\Internal\Http\Controllers\PaymentController;
use Modules\Records\Internal\Http\Controllers\RefundController;
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
    Route::get('/export', [RequestController::class, 'export']);
    Route::post('/', [RequestController::class, 'store'])->middleware('role:R02');
    Route::post('/{request_id}/refund', [RefundController::class, 'complete'])->whereNumber('request_id')->middleware('can:transcript.issue_official');
    Route::get('/{request_id}/refund/receipt', [RefundController::class, 'receipt'])->whereNumber('request_id');
    Route::post('/{request_id}/cancellation', [CancellationController::class, 'submit'])->whereNumber('request_id')->middleware('role:R02');
    Route::post('/{request_id}/cancellation-review', [CancellationController::class, 'review'])->whereNumber('request_id')->middleware('can:transcript.issue_official');
    Route::post('/{request_id}/payment', [PaymentController::class, 'submit'])->whereNumber('request_id')->middleware('role:R02');
    Route::post('/{request_id}/payment-review', [PaymentController::class, 'review'])->whereNumber('request_id')->middleware('can:transcript.issue_official');
    Route::get('/{request_id}/payments/{submission_id}/receipt', [PaymentController::class, 'receipt'])->whereNumber(['request_id', 'submission_id']);
    Route::get('/{request_id}', [RequestController::class, 'show'])->whereNumber('request_id');
    Route::post('/{request_id}/review', [RequestController::class, 'review'])->whereNumber('request_id')->middleware('can:transcript.issue_official');
});
