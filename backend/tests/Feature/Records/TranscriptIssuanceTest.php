<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('requires paid approval, staff authority and complete resolved identity for reviewed preview', function () {
    [$student, $id] = transcriptAtStage('Submitted');
    $this->postJson("/api/v1/records/requests/{$id}/preview")->assertForbidden();
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/preview")->assertConflict();
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $student->update(['identification_type' => null]);
    $this->postJson("/api/v1/records/requests/{$id}/preview")->assertConflict();
    $student->update(['identification_type' => 'passport']);
    $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()
        ->assertJsonPath('data.snapshot.identity.identification_type', 'passport')
        ->assertJsonStructure(['data' => ['review_token', 'snapshot']]);
    $this->assertDatabaseCount('transcript_issuances', 0);
});

it('certifies only a current reviewed copy and publishes its immutable bilingual snapshot', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => str_repeat('x', 64)])->assertConflict();
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    $student->update(['name_ar' => 'اسم جديد']);
    $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertConflict();
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    $issued = $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertCreated()->json('data');
    expect($issued['snapshot'])->toEqual($review['snapshot']);
    $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertConflict();
    $this->assertDatabaseCount('transcript_issuances', 1);
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/records/requests/{$id}")->assertJsonPath('data.status', 'Completed');
    $this->postJson("/api/v1/records/requests/{$id}/cancellation")->assertConflict();
    $student->update(['name_ar' => 'اسم لاحق', 'lifecycle_status' => 'Terminated']);
    $this->getJson('/api/v1/records/issuances/'.$issued['id'])->assertOk()->assertJsonPath('data.snapshot.identity.name_ar', 'اسم جديد');
    $this->app['auth']->forgetGuards();
    $this->getJson($issued['verification_url'])->assertOk()->assertJsonPath('data.snapshot.identity.name_ar', 'اسم جديد')
        ->assertJsonMissingPath('data.request_id')->assertJsonMissingPath('data.snapshot.payments');
    $this->getJson('/api/v1/records/verify/unknown-token')->assertNotFound();
});

it('blocks reviewed issuance while cancellation is pending', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation")->assertOk();
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertConflict();
    $this->assertDatabaseCount('transcript_issuances', 0);
});

it('records collection exactly once without changing certification or permitting post-issuance cancellation', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    // Delivery is selected at submission; use the immutable request fixture before certification.
    DB::table('transcript_requests')->where('id', $id)->update(['delivery_method' => 'collection']);
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    $issued = $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertCreated()->json('data');
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertConflict();
    $this->postJson("/api/v1/records/requests/{$id}/collection")->assertForbidden();
    $this->postJson("/api/v1/records/requests/{$id}/cancellation")->assertConflict();
    $staff = actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/records/requests/{$id}/collection")->assertOk()->assertJsonPath('data.status', 'Completed');
    $this->postJson("/api/v1/records/requests/{$id}/collection")->assertConflict();
    $this->assertDatabaseHas('transcript_request_transitions', ['transcript_request_id' => $id, 'action' => 'collected', 'actor_user_id' => $staff->id]);
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated();
    $this->getJson('/api/v1/records/issuances/'.$issued['id'])->assertJsonPath('data.snapshot', $issued['snapshot']);
});

it('revokes validity once, preserves private history and discloses only reference and revoked status publicly', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    $issued = $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertCreated()->json('data');
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/records/issuances/'.$issued['id'].'/revocation', ['reason' => 'Not authorized'])->assertForbidden();
    $staff = actingAsRole(Role::StudentAffairs);
    $this->postJson('/api/v1/records/issuances/'.$issued['id'].'/revocation')->assertUnprocessable();
    $this->postJson('/api/v1/records/issuances/'.$issued['id'].'/revocation', ['reason' => 'Corrected source required'])->assertOk()
        ->assertJsonPath('data.status', 'Revoked')->assertJsonPath('data.revocation.actor_user_id', $staff->id);
    $this->postJson('/api/v1/records/issuances/'.$issued['id'].'/revocation', ['reason' => 'Retry'])->assertConflict();
    $this->getJson($issued['verification_url'])->assertExactJson(['data' => ['reference' => $issued['reference'], 'status' => 'Revoked']]);
    $this->getJson("/api/v1/records/requests/{$id}")->assertJsonPath('data.status', 'Completed')->assertJsonPath('data.refund', null);
    Sanctum::actingAs($student->advisor);
    $this->getJson('/api/v1/records/issuances/'.$issued['id'])->assertOk()->assertJsonPath('data.snapshot', $issued['snapshot']);
    $this->getJson("/api/v1/records/requests/{$id}")->assertForbidden();
});

it('scopes searchable issuance history and token-free exports to live readers', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    $issued = $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertCreated()->json('data');
    Sanctum::actingAs($student->advisor);
    $this->getJson('/api/v1/records/issuances?student_id='.$student->id)->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.reference', $issued['reference'])->assertJsonMissingPath('data.0.verification_url');
    $this->get('/api/v1/records/issuances/export?status=Valid')->assertOk();
    $former = $student->advisor;
    $replacement = User::factory()->create();
    $replacement->assignRole(Role::Advisor);
    $student->update(['advisor_id' => $replacement->id]);
    $this->getJson('/api/v1/records/issuances')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/records/issuances/'.$issued['id'])->assertForbidden();
    Sanctum::actingAs($replacement);
    $this->getJson('/api/v1/records/issuances')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/records/issuances?verification_token=secret')->assertUnprocessable();
});

it('invalidates complete reviewed content after contributing academic or bilingual owner changes', function (string $change) {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);
    $sheet = GradeSheet::factory()->create(['course_offering_id' => $enrollment->course_offering_id,
        'status' => GradeSheetStatus::Finalized]);
    $grade = Grade::factory()->withMark(95)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    match ($change) {
        'grade' => $grade->update(Grade::derivedFrom(80)),
        'incomplete' => $enrollment->update(['course_status' => 'Incomplete']),
        'catalog' => Course::query()->whereKey($enrollment->offering->course_id)->update(['name_ar' => 'اسم مقرر محدث']),
        'program' => Program::query()->whereKey($student->program_id)->update(['name_en' => 'Updated program']),
        'lifecycle' => $student->update(['lifecycle_status' => 'Terminated']),
        'decision' => ExemptionDecision::create(['student_id' => $student->id, 'curriculum_version_id' => $student->curriculum_version_id, 'target_course_id' => $enrollment->offering->course_id, 'target_course_code' => 'EXM-TEST', 'preparatory_requirement_role' => 'EnglishLanguage', 'credit_hours' => 1, 'source' => 'automatic', 'reason_codes' => ['IELTS'], 'source_evidence' => [], 'approved_at' => now()]),
    };
    $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertConflict();
    $this->assertDatabaseCount('transcript_issuances', 0);
})->with(['grade', 'incomplete', 'catalog', 'program', 'lifecycle', 'decision']);
