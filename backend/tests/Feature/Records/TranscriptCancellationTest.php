<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

function transcriptAtStage(string $stage): array
{
    Storage::fake('local');
    $student = recordsStudent();
    configureRecordsFee();
    Sanctum::actingAs($student->user);
    $id = test()->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated()->json('data.id');
    if ($stage === 'Submitted') {
        return [$student, $id];
    }
    actingAsRole(Role::StudentAffairs);
    test()->postJson("/api/v1/records/requests/{$id}/review", ['decision' => 'approve', 'reason' => 'Reviewed'])->assertOk();
    if ($stage === 'AwaitingPayment') {
        return [$student, $id];
    }
    Sanctum::actingAs($student->user);
    test()->postJson("/api/v1/records/requests/{$id}/payment", ['reference' => 'PAY', 'receipt' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')])->assertOk();
    if ($stage !== 'PaymentUnderReview') {
        actingAsRole(Role::StudentAffairs);
        test()->postJson("/api/v1/records/requests/{$id}/payment-review", $stage === 'ReadyForIssuance'
            ? ['decision' => 'verify', 'amount' => '125.50'] : ['decision' => 'return', 'reason' => 'Correct evidence'])->assertOk();
    }

    return [$student, $id];
}

it('pauses every pre-issuance stage and restores the exact prior stage on cancellation denial', function (string $stage) {
    [$student, $id] = transcriptAtStage($stage);
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation", ['reason' => 'No longer needed'])->assertOk()
        ->assertJsonPath('data.status', 'CancellationPending')->assertJsonPath('data.cancellation_prior_status', $stage);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation", ['reason' => 'Retry'])->assertConflict();
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/payment-review", ['decision' => 'verify', 'amount' => '125.50'])->assertConflict();
    $this->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'reject'])->assertUnprocessable();
    $this->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'reject', 'reason' => 'Student confirmed need'])->assertOk()
        ->assertJsonPath('data.status', $stage);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'approve', 'reason' => 'Retry'])->assertConflict();
})->with(['Submitted', 'AwaitingPayment', 'PaymentUnderReview', 'PaymentCorrectionRequired', 'ReadyForIssuance']);

it('cancels unpaid requests without inventing a refund from an uploaded receipt', function () {
    [$student, $id] = transcriptAtStage('PaymentUnderReview');
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation", ['reason' => 'Stop service'])->assertOk();
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'approve', 'reason' => 'No verified payment'])->assertOk()
        ->assertJsonPath('data.status', 'Cancelled')->assertJsonPath('data.refund', null)->assertJsonCount(1, 'data.payments');
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated();
});

it('creates one full verified-payment refund obligation and keeps the request open until settlement', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $student->update(['lifecycle_status' => 'Terminated']);
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation", ['reason' => 'Cancel paid service'])->assertOk();
    Sanctum::actingAs($student->advisor);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'approve', 'reason' => 'Unauthorized'])->assertForbidden();
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'approve', 'reason' => 'Full refund owed'])->assertOk()
        ->assertJsonPath('data.status', 'RefundPending')->assertJsonPath('data.refund.amount', '125.50')
        ->assertJsonPath('data.refund.completed_at', null);
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertConflict();
});
