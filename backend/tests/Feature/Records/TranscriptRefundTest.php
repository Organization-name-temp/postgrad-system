<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

function paidCancelledTranscript(): array
{
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    Sanctum::actingAs($student->user);
    test()->postJson("/api/v1/records/requests/{$id}/cancellation")->assertOk();
    actingAsRole(Role::StudentAffairs);
    test()->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'approve', 'reason' => 'Refund full fee'])->assertOk();

    return [$student, $id];
}

it('records one full manual refund with private evidence and releases the open-request limit', function () {
    [$student, $id] = paidCancelledTranscript();
    $receipt = UploadedFile::fake()->create('refund.pdf', 10, 'application/pdf');
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/records/requests/{$id}/refund", ['amount' => '125.50', 'reference' => 'REF-ONE', 'receipt' => $receipt])->assertForbidden();
    $staff = actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/refund", ['amount' => '125.50', 'reference' => 'REF-ONE', 'receipt' => $receipt])->assertOk()
        ->assertJsonPath('data.status', 'Cancelled')->assertJsonPath('data.refund.reference', 'REF-ONE')
        ->assertJsonPath('data.refund.completed_by', $staff->id)->assertJsonPath('data.refund.amount', '125.50');
    $this->postJson("/api/v1/records/requests/{$id}/refund", ['amount' => '125.50', 'reference' => 'REF-TWO', 'receipt' => $receipt])->assertConflict();
    Sanctum::actingAs($student->advisor);
    $this->getJson("/api/v1/records/requests/{$id}/refund/receipt")->assertForbidden();
    Sanctum::actingAs($student->user);
    $this->get("/api/v1/records/requests/{$id}/refund/receipt")->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->getJson("/api/v1/records/requests/{$id}")->assertOk()->assertJsonCount(1, 'data.payments')
        ->assertJsonPath('data.verified_payment.amount', '125.50');
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'collection'])->assertCreated();
    $this->assertDatabaseHas('audit_log', ['action' => 'records.refund_completed']);
});

it('refuses partial, excessive, or undocumented refunds without settling the obligation', function (string $amount, bool $receipt) {
    [, $id] = paidCancelledTranscript();
    $data = ['amount' => $amount, 'reference' => 'REF'];
    if ($receipt) {
        $data['receipt'] = UploadedFile::fake()->create('refund.pdf', 10, 'application/pdf');
    }
    $this->postJson("/api/v1/records/requests/{$id}/refund", $data)->assertUnprocessable();
    $this->getJson("/api/v1/records/requests/{$id}")->assertOk()->assertJsonPath('data.status', 'RefundPending')
        ->assertJsonPath('data.refund.completed_at', null);
})->with([['125.49', true], ['125.51', true], ['0', true], ['125.50', false]]);
