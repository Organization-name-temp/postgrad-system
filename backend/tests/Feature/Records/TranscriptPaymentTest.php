<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

function approvedTranscriptRequest(): array
{
    $student = recordsStudent();
    configureRecordsFee();
    Sanctum::actingAs($student->user);
    $id = test()->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated()->json('data.id');
    actingAsRole(Role::StudentAffairs);
    test()->postJson("/api/v1/records/requests/{$id}/review", ['decision' => 'approve', 'reason' => 'Verified request'])->assertOk();

    return [$student, $id];
}

it('preserves corrected private payment submissions and accepts exactly the captured fee once', function () {
    Storage::fake('local');
    [$student, $id] = approvedTranscriptRequest();
    Sanctum::actingAs($student->user);
    $evidence = $this->postJson("/api/v1/records/requests/{$id}/payment", ['reference' => 'PAY-ONE', 'receipt' => UploadedFile::fake()->create('payment.pdf', 10, 'application/pdf')])
        ->assertOk()->assertJsonPath('data.status', 'PaymentUnderReview')->json('data.payments.0.id');
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/payment-review", ['decision' => 'verify', 'amount' => '125.49'])->assertUnprocessable();
    $this->postJson("/api/v1/records/requests/{$id}/payment-review", ['decision' => 'return', 'reason' => 'Unclear receipt'])->assertOk()->assertJsonPath('data.status', 'PaymentCorrectionRequired');
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/records/requests/{$id}/payment", ['reference' => 'PAY-TWO', 'receipt' => UploadedFile::fake()->create('corrected.pdf', 10, 'application/pdf')])
        ->assertOk()->assertJsonCount(2, 'data.payments')->assertJsonPath('data.payments.0.reference', 'PAY-ONE');
    $this->get("/api/v1/records/requests/{$id}/payments/{$evidence}/receipt")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    Sanctum::actingAs($student->advisor);
    $this->getJson("/api/v1/records/requests/{$id}/payments/{$evidence}/receipt")->assertForbidden();
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/payment-review", ['decision' => 'verify', 'amount' => '125.50'])->assertOk()->assertJsonPath('data.status', 'ReadyForIssuance')
        ->assertJsonPath('data.verified_payment.amount', '125.50');
    $this->postJson("/api/v1/records/requests/{$id}/payment-review", ['decision' => 'verify', 'amount' => '125.50'])->assertConflict();
});

it('refuses missing or invalid receipts and submissions outside an approved payment stage', function () {
    [$student, $id] = approvedTranscriptRequest();
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/records/requests/{$id}/payment", ['reference' => 'PAY'])->assertUnprocessable();
    $this->postJson("/api/v1/records/requests/{$id}/payment", ['reference' => 'PAY', 'receipt' => UploadedFile::fake()->create('receipt.exe', 10, 'application/octet-stream')])->assertUnprocessable();
    $this->getJson("/api/v1/records/requests/{$id}")->assertOk()->assertJsonPath('data.status', 'AwaitingPayment');
});
