<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Admissions\Public\AdmissionDocumentSummary;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\TransferRequestTransition;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Notifications\Internal\Models\Notification;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

function transferTranscriptId(): string
{
    return '01ARZ3NDEKTSV4RRFFQ69G5FAV';
}

beforeEach(function (): void {
    app()->instance(AdmissionDocuments::class, new class implements AdmissionDocuments
    {
        public function forApplicant(int $userId): array
        {
            return [new AdmissionDocumentSummary(transferTranscriptId(), 'transcript', 'transcript.pdf')];
        }

        public function downloadForApplicant(int $userId, string $documentId): ?StreamedResponse
        {
            return $documentId === transferTranscriptId()
                ? response()->streamDownload(fn () => print 'transcript bytes', 'transcript.pdf')
                : null;
        }
    });
});

/** @return array{Student, User, User, User, Course} */
function transferWorld(): array
{
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $course = Course::factory()->create([
        'department_id' => $department->id,
        'code' => 'CSE-'.(501 + Course::query()->count()),
        'lecture_ch' => 3,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 3,
    ]);
    $version = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => ['curriculum' => [[
            'course_id' => $course->id,
            'course_code' => $course->code,
            'course_total_ch' => 3,
            'category' => CourseCategory::ProgramCore->value,
            'preparatory_requirement_role' => null,
            'ordinal' => 0,
        ]]],
        'changed_at' => now(),
    ]);
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $student = Student::factory()->create([
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'advisor_id' => $advisor->id,
        'admitted_at' => '2026-01-31 10:15:00',
    ]);
    $student->user->assignRole(Role::Student);
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create(['user_id' => $head->id, 'department_id' => $department->id]);
    $council = User::factory()->create();
    $council->assignRole(Role::Council);

    return [$student, $advisor, $head, $council, $course];
}

/** @return array<string, mixed> */
function transferEvidence(int $targetCourseId, string $institution = 'Université de Montréal'): array
{
    return [
        'target_course_id' => $targetCourseId,
        'external_institution' => $institution,
        'external_course_code' => 'IFT 6251',
        'external_course_name' => 'Machine Learning',
        'source_credit_hours' => '12.50',
        'external_grade' => 'Très bien (B)',
        'document_ids' => [transferTranscriptId()],
        'student_note' => 'Please assess this course.',
    ];
}

function createAndSubmitTransfer(Student $student, Course $course, string $actionId = '11111111-1111-4111-8111-111111111111'): int
{
    Sanctum::actingAs($student->user);
    $id = (int) test()->postJson('/api/v1/credit-decisions/transfer-requests', transferEvidence($course->id))
        ->assertCreated()->json('data.id');
    test()->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => $actionId,
    ])->assertOk();

    return $id;
}

function advanceTransferToCouncil(int $id, User $advisor, User $head, string $grade = 'B+'): void
{
    Sanctum::actingAs($advisor);
    test()->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '22222222-2222-4222-8222-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT),
    ])->assertOk();
    Sanctum::actingAs($head);
    test()->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'normalized_grade' => $grade, 'expected_revision' => 1,
        'action_id' => '33333333-3333-4333-8333-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT),
    ])->assertOk();
}

function transferRequestFor(Student $student, Course $course, TransferRequestStatus $status, string $institution = 'أَحْمَد University'): TransferRequest
{
    return TransferRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => $course->total_ch,
        'external_institution' => $institution,
        'external_course_code' => 'EXT-501',
        'external_course_name' => 'External Algorithms',
        'source_credit_hours' => '4.00',
        'external_grade' => 'Excellent',
        'document_ids' => [transferTranscriptId()],
        'normalized_grade' => $status === TransferRequestStatus::PendingCouncil ? 'A' : null,
        'verified_by_user_id' => $status === TransferRequestStatus::PendingCouncil ? $student->advisor_id : null,
        'verified_at' => $status === TransferRequestStatus::PendingCouncil ? now() : null,
        'status' => $status,
        'revision' => 1,
        'submitted_at' => $status === TransferRequestStatus::Draft ? null : now(),
    ]);
}

it('blocks a Transfer target already recognized by Waiver', function () {
    [$student, $advisor, $head, $council, $course] = transferWorld();
    $prior = Course::factory()->create([
        'department_id' => $course->department_id,
        'code' => 'CSE-411',
        'level' => 400,
    ]);
    $waiverRequest = WaiverRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'credit_hours' => $course->total_ch,
        'prior_course_id' => $prior->id,
        'prior_course_code' => $prior->code,
        'prior_course_level' => 400,
        'prior_result_source' => 'legacy_transcript',
        'prior_letter_grade' => 'A',
        'evidence' => ['official_transcript_document_ids' => [transferTranscriptId()]],
        'status' => WaiverRequestStatus::Approved,
        'revision' => 1,
        'submitted_at' => now(),
        'coverage_percentage' => 90,
        'equivalency_note' => 'Equivalent.',
        'verified_by' => $head->id,
        'verified_at' => now(),
    ]);
    WaiverDecision::query()->create([
        'waiver_request_id' => $waiverRequest->id,
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'credit_hours' => $course->total_ch,
        'prior_course_id' => $prior->id,
        'prior_course_code' => $prior->code,
        'prior_course_level' => 400,
        'prior_result_source' => 'legacy_transcript',
        'prior_letter_grade' => 'A',
        'coverage_percentage' => 90,
        'verified_by' => $head->id,
        'verified_at' => now(),
        'approved_by' => $council->id,
        'approved_at' => now(),
    ]);
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/credit-decisions/transfer-requests/targets')
        ->assertOk()->assertJsonPath('data.0.already_transferred', true);
    $id = (int) $this->postJson('/api/v1/credit-decisions/transfer-requests', transferEvidence($course->id))
        ->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => '10101010-1010-4010-8010-101010101010',
    ])->assertConflict();
});

it('recognizes external study through Advisor Head and Council without creating an Enrollment or Grade', function () {
    [$student, $advisor, $head, $council, $course] = transferWorld();
    $this->travelTo('2026-02-28 10:15:00');

    Sanctum::actingAs($student->user);
    $created = $this->postJson('/api/v1/credit-decisions/transfer-requests', [
        'target_course_id' => $course->id,
        'external_institution' => 'Université de Montréal',
        'external_course_code' => 'IFT 6251',
        'external_course_name' => 'Machine Learning',
        'source_credit_hours' => '12.50',
        'external_grade' => 'Très bien (B)',
        'document_ids' => [transferTranscriptId()],
        'student_note' => 'Please assess this course.',
    ])->assertCreated()
        ->assertJsonPath('data.status', 'Draft')
        ->assertJsonPath('data.external_grade', 'Très bien (B)')
        ->assertJsonPath('data.source_credit_hours', '12.50')
        ->assertJsonPath('data.target_credit_hours', 3);
    $id = $created->json('data.id');

    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => '11111111-1111-4111-8111-111111111111',
    ])->assertOk()->assertJsonPath('data.status', 'PendingAdvisor');

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '22222222-2222-4222-8222-222222222222',
    ])->assertOk()->assertJsonPath('data.status', 'PendingDepartmentHead');

    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'normalized_grade' => 'B+', 'expected_revision' => 1,
        'action_id' => '33333333-3333-4333-8333-333333333333',
    ])->assertOk()
        ->assertJsonPath('data.status', 'PendingCouncil')
        ->assertJsonPath('data.normalized_grade', 'B+');

    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/council-decision", [
        'action' => 'approve', 'expected_revision' => 1,
        'action_id' => '44444444-4444-4444-8444-444444444444',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Approved')
        ->assertJsonPath('data.decision.target_credit_hours', 3)
        ->assertJsonPath('data.decision.source_credit_hours', '12.50')
        ->assertJsonPath('data.decision.normalized_grade', 'B+');

    expect(TransferDecision::query()->sole()->target_credit_hours)->toBe(3);
    $this->assertDatabaseCount('enrollments', 0);
    $this->assertDatabaseCount('grades', 0);
    $this->assertDatabaseHas('audit_log', ['action' => 'transfer.request.approved']);
    $decision = TransferDecision::query()->sole();
    $transition = TransferRequestTransition::query()->latest('id')->firstOrFail();
    expect(fn () => $decision->update(['normalized_grade' => 'A']))
        ->toThrow(LogicException::class, 'immutable')
        ->and(fn () => $decision->delete())->toThrow(LogicException::class, 'immutable')
        ->and(fn () => $transition->update(['reason' => 'changed']))->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $transition->delete())->toThrow(LogicException::class, 'append-only');
});

it('accepts only owned admission evidence and requires a transcript', function () {
    [$student, , , , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    $supplementId = '01ARZ3NDEKTSV4RRFFQ69G5FAW';
    app()->instance(AdmissionDocuments::class, new class($student->user_id, $supplementId) implements AdmissionDocuments
    {
        public function __construct(private readonly int $ownerId, private readonly string $supplementId) {}

        public function forApplicant(int $userId): array
        {
            if ($userId !== $this->ownerId) {
                return [];
            }

            return [
                new AdmissionDocumentSummary(transferTranscriptId(), 'transcript', 'transcript.pdf'),
                new AdmissionDocumentSummary($this->supplementId, 'additional_certificate', 'syllabus.pdf'),
            ];
        }

        public function downloadForApplicant(int $userId, string $documentId): ?StreamedResponse
        {
            return $userId === $this->ownerId && in_array($documentId, [transferTranscriptId(), $this->supplementId], true)
                ? response()->streamDownload(fn () => print 'evidence bytes', 'evidence.pdf')
                : null;
        }
    });
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/credit-decisions/transfer-requests/evidence-documents')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', transferTranscriptId());

    $payload = transferEvidence($course->id);
    $payload['document_ids'] = [$supplementId];
    $this->postJson('/api/v1/credit-decisions/transfer-requests', $payload)
        ->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.invalid_documents'));

    $payload['document_ids'] = ['01ARZ3NDEKTSV4RRFFQ69G5FAX'];
    $this->postJson('/api/v1/credit-decisions/transfer-requests', $payload)
        ->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.invalid_documents'));

    $payload['document_ids'] = [transferTranscriptId(), $supplementId];
    $this->postJson('/api/v1/credit-decisions/transfer-requests', $payload)->assertCreated();
});

it('lets only the live scoped reviewer open named supporting evidence', function () {
    [$student, $advisor, $head, , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    $id = createAndSubmitTransfer($student, $course);
    $documentUrl = "/api/v1/credit-decisions/transfer-requests/{$id}/evidence-documents/".transferTranscriptId();

    Sanctum::actingAs($advisor);
    $this->getJson('/api/v1/credit-decisions/transfer-requests/advisor-queue')
        ->assertOk()
        ->assertJsonPath('data.0.evidence_documents.0.original_filename', 'transcript.pdf')
        ->assertJsonPath('data.0.evidence_documents.0.download_url', $documentUrl);
    $this->get($documentUrl)->assertOk()->assertHeader('content-disposition');

    $otherAdvisor = User::factory()->create();
    $otherAdvisor->assignRole(Role::Advisor);
    Sanctum::actingAs($otherAdvisor);
    $this->get($documentUrl)->assertForbidden();

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => 'edededed-eded-4ded-8ded-edededededed',
    ])->assertOk();
    $this->get($documentUrl)->assertForbidden();

    Sanctum::actingAs($head);
    $this->get($documentUrl)->assertOk();
});

it('notifies the active reviewer without notifying an Advisor about their own return', function () {
    [$student, $advisor, , , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    $id = createAndSubmitTransfer($student, $course);

    expect(Notification::query()->where('user_id', $advisor->id)->where('type', 'transfer.request_submitted')->count())->toBe(1);
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", [
        'action' => 'return', 'reason' => 'Attach a clearer transcript.', 'expected_revision' => 1,
        'action_id' => 'abababab-abab-4bab-8bab-abababababab',
    ])->assertOk()->assertJsonPath('data.status', 'Returned');

    expect(Notification::query()->where('user_id', $student->user_id)->where('type', 'transfer.request_returned')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $advisor->id)->where('type', 'transfer.request_returned')->count())->toBe(0);
});

it('enforces the fixed Role and live scope at every review transition', function () {
    [$student, $advisor, $head, , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    $id = createAndSubmitTransfer($student, $course);

    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => 'acacacac-acac-4cac-8cac-acacacacacac',
    ])->assertForbidden();

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'normalized_grade' => 'A', 'expected_revision' => 1,
        'action_id' => 'adadadad-adad-4dad-8dad-adadadadadad',
    ])->assertForbidden();

    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/council-decision", [
        'action' => 'approve', 'expected_revision' => 1,
        'action_id' => 'aeaeaeae-aeae-4eae-8eae-aeaeaeaeaeae',
    ])->assertForbidden();
});

it('enforces the inclusive clamped one-calendar-month submission deadline bilingually', function () {
    [$student, , , , $course] = transferWorld();
    $this->travelTo('2026-02-28 10:15:00');
    $id = createAndSubmitTransfer($student, $course);
    expect(TransferRequest::findOrFail($id)->status->value)->toBe('PendingAdvisor');

    [$laterStudent, , , , $laterCourse] = transferWorld();
    $laterStudent->update(['admitted_at' => '2026-01-31 10:15:00']);
    $this->travelTo('2026-02-28 10:15:01');
    Sanctum::actingAs($laterStudent->user);
    $laterId = (int) $this->postJson('/api/v1/credit-decisions/transfer-requests', transferEvidence($laterCourse->id))->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$laterId}/submit", [
        'expected_revision' => 1, 'action_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    ])->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.deadline_passed'));

    app()->setLocale('ar');
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$laterId}/submit", [
        'expected_revision' => 1, 'action_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    ])->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.deadline_passed'));
});

it('invalidates prior review evidence on return revise and resubmit', function () {
    [$student, $advisor, $head, $council, $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    $id = createAndSubmitTransfer($student, $course);
    advanceTransferToCouncil($id, $advisor, $head, 'A');

    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/council-decision", [
        'action' => 'return', 'reason' => 'Attach the signed transcript.', 'expected_revision' => 1,
        'action_id' => '44444444-4444-4444-8444-444444444444',
    ])->assertOk()->assertJsonPath('data.status', 'Returned');

    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '55555555-5555-4555-8555-555555555555',
    ])->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.revision_required'));
    $this->patchJson("/api/v1/credit-decisions/transfer-requests/{$id}", [
        'expected_revision' => 1, 'external_grade' => 'A (excellent)',
    ])->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.normalized_grade', null);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/submit", [
        'expected_revision' => 2, 'action_id' => '66666666-6666-4666-8666-666666666666',
    ])->assertOk()->assertJsonPath('data.status', 'PendingAdvisor');

    expect(TransferRequestTransition::query()->where('action', 'verified_by_department_head')->sole()->revision)->toBe(1)
        ->and(TransferRequest::findOrFail($id)->revision)->toBe(2);
    Sanctum::actingAs($student->user);
    $this->getJson("/api/v1/credit-decisions/transfer-requests/{$id}")
        ->assertJsonPath('data.transitions.2.current_revision', false);
});

it('accepts only the four eligible normalized grades and protects stale actions', function (string $grade) {
    [$student, $advisor, $head, , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    $id = createAndSubmitTransfer($student, $course);
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '77777777-7777-4777-8777-777777777777',
    ])->assertOk();
    Sanctum::actingAs($head);
    $response = $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'normalized_grade' => $grade, 'expected_revision' => 1,
        'action_id' => '88888888-8888-4888-8888-888888888888',
    ]);
    if (in_array($grade, ['A+', 'A', 'B+', 'B'], true)) {
        $response->assertOk()->assertJsonPath('data.normalized_grade', $grade);
    } else {
        $response->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.normalized_grade_ineligible'));
    }
})->with(['A+', 'A', 'B+', 'B', 'C+']);

it('rechecks live Advisor and Head scope before honoring an idempotent replay', function () {
    [$student, $advisor, $head, , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    $id = createAndSubmitTransfer($student, $course);
    $advisorPayload = [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '99999999-9999-4999-8999-999999999999',
    ];
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", $advisorPayload)->assertOk();
    $student->update(['advisor_id' => User::factory()->create()->id]);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/advisor-decision", $advisorPayload)->assertForbidden();

    $headPayload = [
        'action' => 'verify', 'normalized_grade' => 'A', 'expected_revision' => 1,
        'action_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaab',
    ];
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/department-head-decision", $headPayload)->assertOk();
    DepartmentHeadAssignment::query()->where('user_id', $head->id)->delete();
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/department-head-decision", $headPayload)->assertForbidden();
});

it('uses target Course hours for the lifetime cap and exposes decisions through the Public facade', function () {
    [$student, $advisor, $head, $council, $course] = transferWorld();
    $department = $course->department;
    $second = Course::factory()->create(['department_id' => $department->id, 'code' => 'CSE-502', 'total_ch' => 7, 'lecture_ch' => 7, 'tutorial_ch' => 0, 'lab_ch' => 0]);
    $version = CatalogVersion::findOrFail($student->curriculum_version_id);
    $snapshot = $version->snapshot;
    $snapshot['curriculum'][] = ['course_id' => $second->id, 'course_code' => $second->code, 'course_total_ch' => 7, 'category' => CourseCategory::ProgramCore->value, 'preparatory_requirement_role' => null, 'ordinal' => 1];
    $version->update(['snapshot' => $snapshot]);
    $this->travelTo('2026-02-10 10:15:00');

    $firstId = createAndSubmitTransfer($student, $course);
    advanceTransferToCouncil($firstId, $advisor, $head, 'B');
    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$firstId}/council-decision", [
        'action' => 'approve', 'expected_revision' => 1,
        'action_id' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbc',
    ])->assertOk();

    Sanctum::actingAs($student->user);
    $secondId = (int) $this->postJson('/api/v1/credit-decisions/transfer-requests', transferEvidence($second->id, 'Tiny source'))->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/credit-decisions/transfer-requests/{$secondId}", ['expected_revision' => 1, 'source_credit_hours' => '1.00'])->assertOk();
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$secondId}/submit", ['expected_revision' => 2, 'action_id' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc'])->assertOk();
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$secondId}/advisor-decision", ['action' => 'recommend', 'expected_revision' => 2, 'action_id' => 'dddddddd-dddd-4ddd-8ddd-dddddddddddd'])->assertOk();
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$secondId}/department-head-decision", ['action' => 'verify', 'normalized_grade' => 'A+', 'expected_revision' => 2, 'action_id' => 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee'])->assertOk();
    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$secondId}/council-decision", ['action' => 'approve', 'expected_revision' => 2, 'action_id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff'])
        ->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.cap_exceeded'));

    $summary = app(TransferDecisions::class)->effectiveForStudent($student->id);
    expect($summary)->toHaveCount(1)
        ->and($summary[0]->targetCreditHours)->toBe(3)
        ->and($summary[0]->sourceCreditHours)->toBe('12.50');
});

it('runs Identity StudentGuard before workflow state checks on every mutation', function () {
    [$student, , , , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    Sanctum::actingAs($student->user);
    $id = (int) $this->postJson('/api/v1/credit-decisions/transfer-requests', transferEvidence($course->id))->assertCreated()->json('data.id');
    $student->update(['lifecycle_status' => StudentLifecycleStatus::Terminated]);

    foreach ([
        ['PATCH', "/api/v1/credit-decisions/transfer-requests/{$id}", ['expected_revision' => 1, 'external_grade' => 'A']],
        ['DELETE', "/api/v1/credit-decisions/transfer-requests/{$id}", ['expected_revision' => 1]],
        ['POST', "/api/v1/credit-decisions/transfer-requests/{$id}/submit", ['expected_revision' => 1, 'action_id' => '12121212-1212-4212-8212-121212121212']],
    ] as [$method, $url, $payload]) {
        $this->json($method, $url, $payload)->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.student_inactive'));
    }
});

it('provides scoped pagination search and formula-safe audited XLSX export', function () {
    [$student, $advisor, , , $course] = transferWorld();
    $this->travelTo('2026-02-10 10:15:00');
    Sanctum::actingAs($student->user);
    foreach ([
        '=HYPERLINK("https://evil.invalid","click")',
        'Kyoto University',
    ] as $index => $institution) {
        $id = (int) $this->postJson('/api/v1/credit-decisions/transfer-requests', transferEvidence($course->id, $institution))->assertCreated()->json('data.id');
        $this->postJson("/api/v1/credit-decisions/transfer-requests/{$id}/submit", [
            'expected_revision' => 1,
            'action_id' => $index === 0 ? '31313131-3131-4131-8131-313131313131' : '41414141-4141-4141-8141-414141414141',
        ])->assertOk();
    }
    $this->getJson('/api/v1/credit-decisions/transfer-requests?per_page=1')
        ->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2);

    Sanctum::actingAs($advisor);
    $this->getJson('/api/v1/credit-decisions/transfer-requests/advisor-queue?q=Kyoto&per_page=1')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.external_institution', 'Kyoto University');
    $this->withHeader('Accept-Language', 'ar')->getJson('/api/v1/credit-decisions/transfer-requests/advisor-queue?unknown=1')
        ->assertUnprocessable()->assertJsonPath('message', 'حقل استعلام المجموعة غير معروف: unknown.');

    $response = $this->get('/api/v1/credit-decisions/transfer-requests/advisor-queue/export')->assertOk()->assertDownload('advisor-transfer-queue.xlsx');
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    expect($rows)->toHaveCount(3)
        ->and($rows[1])->toContain('=HYPERLINK("https://evil.invalid","click")')
        ->and(AuditLog::query()->where('action', 'transfer.requests.exported')->latest('id')->firstOrFail()->metadata['row_count'])->toBe(2);

    $outsideAdvisor = User::factory()->create();
    $outsideAdvisor->assignRole(Role::Advisor);
    Sanctum::actingAs($outsideAdvisor);
    $this->getJson('/api/v1/credit-decisions/transfer-requests/advisor-queue')->assertOk()->assertJsonPath('meta.total', 0);
});

it('applies the shared actor-aware collection contract to every Transfer surface', function (string $surface) {
    [$student, $advisor, $head, $council, $course] = transferWorld();
    $status = match ($surface) {
        'student' => TransferRequestStatus::Draft,
        'advisor' => TransferRequestStatus::PendingAdvisor,
        'department_head' => TransferRequestStatus::PendingDepartmentHead,
        'council' => TransferRequestStatus::PendingCouncil,
    };
    transferRequestFor($student, $course, $status);
    transferRequestFor($student, $course, $status, 'Kyoto University');

    $outsideDepartment = Department::factory()->create();
    $outsideProgram = Program::factory()->create(['department_id' => $outsideDepartment->id]);
    $outsideCourse = Course::factory()->create(['department_id' => $outsideDepartment->id, 'code' => 'CSE-OUT', 'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3]);
    $outsideAdvisor = User::factory()->create();
    $outsideAdvisor->assignRole(Role::Advisor);
    $outsideStudent = Student::factory()->create(['program_id' => $outsideProgram->id, 'curriculum_version_id' => $student->curriculum_version_id, 'advisor_id' => $outsideAdvisor->id]);
    transferRequestFor($outsideStudent, $outsideCourse, $status);

    [$actor, $endpoint] = match ($surface) {
        'student' => [$student->user, '/api/v1/credit-decisions/transfer-requests'],
        'advisor' => [$advisor, '/api/v1/credit-decisions/transfer-requests/advisor-queue'],
        'department_head' => [$head, '/api/v1/credit-decisions/transfer-requests/department-head-queue'],
        'council' => [$council, '/api/v1/credit-decisions/transfer-requests/council-queue'],
    };
    Sanctum::actingAs($actor);
    $expectedScoped = $surface === 'council' ? 3 : 2;

    $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?unknown_field=1')
        ->assertUnprocessable()->assertJsonPath('message', 'حقل استعلام المجموعة غير معروف: unknown_field.');
    $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?target_course_id=abc')
        ->assertUnprocessable()->assertJsonPath('message', 'يجب أن يحتوي حقل استعلام المجموعة target_course_id على أعداد صحيحة موجبة فقط.');
    $this->getJson($endpoint.'?target_course_id='.$course->id.','.$outsideCourse->id)
        ->assertOk()->assertJsonPath('meta.total', $expectedScoped);
    $this->getJson($endpoint.'?target_course_id='.$outsideCourse->id)
        ->assertOk()->assertJsonPath('meta.total', $surface === 'council' ? 1 : 0);
    $this->getJson($endpoint.'?national_id=29001011234567')
        ->assertOk()->assertJsonPath('meta.total', $expectedScoped)
        ->assertJsonMissingPath('data.0.national_id')->assertJsonMissingPath('data.0.student.national_id');
    $page = $this->getJson($endpoint.'?per_page=1')->assertOk()
        ->assertJsonPath('meta.total', $expectedScoped)->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.last_page', $expectedScoped);
    expect($page->json('data'))->toHaveCount(1);

    if ($surface === 'student') {
        $this->getJson($endpoint.'?q=احمد')->assertOk()->assertJsonPath('meta.total', 1);
    } else {
        $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?student_id=bad')
            ->assertUnprocessable()->assertJsonPath('message', 'يجب أن يحتوي حقل استعلام المجموعة student_id على أعداد صحيحة موجبة فقط.');
    }
    $this->get($endpoint.'/export?target_course_id=999999')->assertOk()->assertDownload();
    expect(AuditLog::query()->where('action', 'transfer.requests.exported')->latest('id')->firstOrFail()->metadata['row_count'])->toBe(0);
})->with('transfer collections');
