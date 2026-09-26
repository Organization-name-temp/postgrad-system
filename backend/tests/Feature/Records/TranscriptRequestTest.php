<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

function configureRecordsFee(): void
{
    actingAsRole(Role::StudentAffairs);
    test()->putJson('/api/v1/records/fee', ['fee' => '125.50', 'instructions_en' => 'Fictional Bank A', 'instructions_ar' => 'بنك وهمي أ'])->assertOk();
}

it('captures one open request and shows captured instructions only after staff approval', function () {
    $student = recordsStudent();
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertConflict();
    configureRecordsFee();
    Sanctum::actingAs($student->user);
    $request = $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated()
        ->assertJsonPath('data.status', 'Submitted')->assertJsonPath('data.fee', '125.50')
        ->assertJsonMissingPath('data.instructions_en')->json('data.id');
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'collection'])->assertConflict();
    actingAsRole(Role::StudentAffairs);
    $this->putJson('/api/v1/records/fee', ['fee' => '200', 'instructions_en' => 'Fictional Bank B', 'instructions_ar' => 'بنك وهمي ب'])->assertOk();
    $this->postJson("/api/v1/records/requests/{$request}/review", ['decision' => 'approve'])->assertUnprocessable();
    $this->postJson("/api/v1/records/requests/{$request}/review", ['decision' => 'approve', 'reason' => 'Identity reviewed'])->assertOk()
        ->assertJsonPath('data.status', 'AwaitingPayment');
    $this->postJson("/api/v1/records/requests/{$request}/review", ['decision' => 'approve', 'reason' => 'Retry'])->assertConflict();
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/requests/{$request}")->assertOk()
        ->assertJsonPath('data.instructions_en', 'Fictional Bank A')->assertJsonPath('data.fee', '125.50')
        ->assertJsonCount(2, 'data.transitions');
    $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.type', 'records.request_changed');
    Sanctum::actingAs($student->advisor);
    $this->getJson("/api/v1/records/requests/{$request}")->assertForbidden();
    $this->postJson("/api/v1/records/requests/{$request}/review", ['decision' => 'reject', 'reason' => 'Unauthorized'])->assertForbidden();
});

it('rejection releases the request limit and Terminated Students retain the service', function () {
    $student = recordsStudent();
    $student->update(['lifecycle_status' => 'Terminated']);
    configureRecordsFee();
    Sanctum::actingAs($student->user);
    $request = $this->postJson('/api/v1/records/requests', ['delivery_method' => 'collection'])->assertCreated()->json('data.id');
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$request}/review", ['decision' => 'reject', 'reason' => 'Duplicate service need'])->assertOk()->assertJsonPath('data.status', 'Rejected');
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated();
    $this->getJson('/api/v1/records/requests')->assertOk()->assertJsonPath('meta.total', 2);
});

it('keeps the service queue unavailable to Advisors and filters staff search through readable admission names', function () {
    $student = recordsStudent();
    $student->update(['name_ar' => 'طالبة خدمة فريدة']);
    configureRecordsFee();
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated()->json('data.id');
    Sanctum::actingAs($student->advisor);
    $this->getJson('/api/v1/records/requests')->assertForbidden();
    actingAsRole(Role::StudentAffairs);
    $this->getJson('/api/v1/records/requests?q=فريدة')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
    $this->getJson('/api/v1/records/requests?q=absent-name')->assertOk()->assertJsonPath('meta.total', 0);
    $this->get('/api/v1/records/requests/export?q=فريدة')->assertOk()->assertDownload('transcript-requests.xlsx');
    $this->assertDatabaseHas('audit_log', ['action' => 'records.requests_exported']);
    $this->getJson('/api/v1/records/requests?verification_token=hidden')->assertUnprocessable();
});

it('reports the owners open request independently of filtered staff or Student queues', function () {
    $student = recordsStudent();
    configureRecordsFee();
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated()->json('data.id');
    $this->getJson('/api/v1/records/service')->assertJsonPath('data.open_request_id', $id);
    $this->getJson("/api/v1/records/requests/{$id}")->assertJsonPath('data.is_owner', true);
    actingAsRole(Role::StudentAffairs);
    $this->getJson('/api/v1/records/service')->assertJsonPath('data.open_request_id', null);
    $this->getJson("/api/v1/records/requests/{$id}")->assertJsonPath('data.is_owner', false);
});
