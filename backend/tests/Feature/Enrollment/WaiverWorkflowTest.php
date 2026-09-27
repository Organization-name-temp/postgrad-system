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
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Internal\Models\WaiverRequestTransition;
use Modules\Enrollment\Public\WaiverDecisions;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Internal\Models\Notification;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\StreamedResponse;

uses(RefreshDatabase::class);

function waiverTranscriptId(): string
{
    return '01ARZ3NDEKTSV4RRFFQ69G5FAW';
}

beforeEach(function (): void {
    app()->instance(AdmissionDocuments::class, new class implements AdmissionDocuments
    {
        public function forApplicant(int $userId): array
        {
            return [new AdmissionDocumentSummary(waiverTranscriptId(), 'transcript', 'legacy-transcript.pdf')];
        }

        public function downloadForApplicant(int $userId, string $documentId): ?StreamedResponse
        {
            return $documentId === waiverTranscriptId()
                ? response()->streamDownload(fn () => print 'legacy transcript bytes', 'legacy-transcript.pdf')
                : null;
        }
    });
});

/** @return array{Student, User, User, User, Course, Course, Enrollment} */
function waiverWorld(): array
{
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $target = Course::factory()->create([
        'department_id' => $department->id,
        'code' => 'CSE-511',
        'level' => CourseLevel::Master,
        'lecture_ch' => 3,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 3,
    ]);
    $prior = Course::factory()->create([
        'department_id' => $department->id,
        'code' => 'CSE-411',
        'level' => CourseLevel::Foundation,
    ]);
    $version = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => ['curriculum' => [[
            'course_id' => $target->id,
            'course_code' => $target->code,
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
    ]);
    $student->user->assignRole(Role::Student);
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create(['user_id' => $head->id, 'department_id' => $department->id]);
    $council = User::factory()->create();
    $council->assignRole(Role::Council);
    $offering = CourseOffering::factory()->create(['course_id' => $prior->id]);
    $priorEnrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'earned' => true,
    ]);

    return [$student, $advisor, $head, $council, $target, $prior, $priorEnrollment];
}

it('takes a qualifying prior E-JUST result through Advisor Head and Council to an immutable Waiver decision', function () {
    [$student, $advisor, $head, $council, $target, , $priorEnrollment] = waiverWorld();

    Sanctum::actingAs($student->user);
    $draft = $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => [
            'source' => 'prs',
            'institution' => 'E-JUST',
            'enrollment_id' => $priorEnrollment->id,
        ],
        'student_note' => 'Prior E-JUST course covers this requirement.',
    ])->assertCreated()->assertJsonPath('data.status', 'Draft');
    $id = $draft->json('data.id');

    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => 'c610e9cb-6491-4a22-9e3f-c00d033853ae',
    ])->assertOk()->assertJsonPath('data.status', 'PendingAdvisor');

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/advisor-decision", [
        'action' => 'recommend',
        'expected_revision' => 1,
        'action_id' => '1ff39f1f-b67c-42f4-bef0-2bdfd74488ec',
    ])->assertOk()->assertJsonPath('data.status', 'PendingDepartmentHead');

    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", [
        'action' => 'verify',
        'coverage_percentage' => 80,
        'equivalency_note' => 'Learning outcomes and assessment align.',
        'expected_revision' => 1,
        'action_id' => '61487425-ff8b-4ded-967c-73eac8037f35',
    ])->assertOk()
        ->assertJsonPath('data.status', 'PendingCouncil')
        ->assertJsonPath('data.head_verification.coverage_percentage', 80);

    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/council-decision", [
        'action' => 'approve',
        'expected_revision' => 1,
        'action_id' => 'bb8a77c8-dfae-4576-9051-eeb04ee334bb',
    ])->assertOk()
        ->assertJsonPath('data.status', 'Approved')
        ->assertJsonPath('data.decision.credit_hours', 3);

    $this->assertDatabaseHas('waiver_decisions', [
        'waiver_request_id' => $id,
        'student_id' => $student->id,
        'target_course_id' => $target->id,
        'credit_hours' => 3,
    ]);
    $this->assertDatabaseCount('enrollments', 1);
    $this->assertDatabaseCount('grades', 0);
    expect(Notification::query()->where('user_id', $advisor->id)->where('type', 'waiver.request_submitted')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $head->id)->where('type', 'waiver.request_recommended')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $council->id)->where('type', 'waiver.request_verified')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $student->user_id)->where('type', 'waiver.request_decided')->count())->toBe(1);
    expect(app(WaiverDecisions::class)->effectiveForStudent((int) $student->id))
        ->toHaveCount(1)
        ->and(app(WaiverDecisions::class)->effectiveForStudent((int) $student->id)[0]->creditHours)
        ->toBe(3);

    $batch = app(WaiverDecisions::class)->effectiveForStudents([(int) $student->id, (int) $student->id, 999999]);
    expect($batch[(int) $student->id])->toEqual(app(WaiverDecisions::class)->effectiveForStudent((int) $student->id))
        ->and($batch)->not->toHaveKey(999999)
        ->and(app(WaiverDecisions::class)->effectiveForStudents([]))->toBe([]);

    expect(fn () => WaiverDecision::query()->where('waiver_request_id', $id)->firstOrFail()->update(['credit_hours' => 4]))
        ->toThrow(LogicException::class, 'Effective Waiver decisions are immutable.')
        ->and(fn () => WaiverDecision::query()->where('waiver_request_id', $id)->firstOrFail()->delete())
        ->toThrow(LogicException::class, 'Effective Waiver decisions are immutable.')
        ->and(fn () => WaiverRequestTransition::query()->where('waiver_request_id', $id)->firstOrFail()->update(['reason' => 'rewritten']))
        ->toThrow(LogicException::class, 'Waiver request transitions are append-only.')
        ->and(fn () => WaiverRequest::query()->findOrFail($id)->delete())
        ->toThrow(LogicException::class, 'Waiver requests cannot be hard-deleted');
});

it('accepts only qualifying official legacy E-JUST results and directs external study to Transfer', function () {
    [$student, $advisor, , , $target, $prior] = waiverWorld();
    Sanctum::actingAs($student->user);

    $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => [
            'source' => 'legacy_transcript',
            'institution' => 'Another University',
            'course_id' => $prior->id,
            'letter_grade' => 'A',
            'official_transcript_document_ids' => [waiverTranscriptId()],
        ],
    ])->assertUnprocessable()->assertJsonPath('message', 'Study outside E-JUST must use the Transfer workflow.');

    foreach (['C', 'D+', 'D', 'F'] as $grade) {
        $this->postJson('/api/v1/credit-decisions/waiver-requests', [
            'target_course_id' => $target->id,
            'prior_result' => [
                'source' => 'legacy_transcript',
                'institution' => 'E-JUST',
                'course_id' => $prior->id,
                'letter_grade' => $grade,
                'official_transcript_document_ids' => [waiverTranscriptId()],
            ],
        ])->assertUnprocessable()->assertJsonPath('message', 'The prior E-JUST Course must be level 400 or above and completed at C+ or higher.');
    }

    $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => [
            'source' => 'legacy_transcript',
            'institution' => 'E-JUST',
            'course_id' => $prior->id,
            'letter_grade' => 'A',
            'official_transcript_document_ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FAX'],
        ],
    ])->assertUnprocessable()->assertJsonPath('message', 'An owned official E-JUST transcript is required for a legacy result.');

    $created = $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => [
            'source' => 'legacy_transcript',
            'institution' => 'E-JUST',
            'course_id' => $prior->id,
            'letter_grade' => 'C+',
            'official_transcript_document_ids' => [waiverTranscriptId()],
        ],
    ])->assertCreated();

    expect($created->json('data.prior_course_level'))->toBe(400)
        ->and($created->json('data.prior_letter_grade'))->toBe('C+')
        ->and($created->json('data.evidence.official_transcript_document_ids'))->toBe([waiverTranscriptId()]);

    $id = (int) $created->json('data.id');
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => '7c3df72d-6158-4263-a6ff-4b76c74eeede',
    ])->assertOk();
    $documentUrl = "/api/v1/credit-decisions/waiver-requests/{$id}/evidence-documents/".waiverTranscriptId();
    Sanctum::actingAs($advisor);
    $this->getJson('/api/v1/credit-decisions/waiver-requests/advisor-queue')
        ->assertOk()
        ->assertJsonPath('data.0.evidence_documents.0.original_filename', 'legacy-transcript.pdf')
        ->assertJsonPath('data.0.evidence_documents.0.download_url', $documentUrl);
    $this->get($documentUrl)->assertOk();

    $otherAdvisor = User::factory()->create();
    $otherAdvisor->assignRole(Role::Advisor);
    Sanctum::actingAs($otherAdvisor);
    $this->get($documentUrl)->assertForbidden();
});

it('blocks a Waiver target already recognized by Transfer', function () {
    [$student, , $head, $council, $target, $prior, $priorEnrollment] = waiverWorld();
    $transferRequest = TransferRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $target->id,
        'target_course_code' => $target->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => $target->total_ch,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-511',
        'external_course_name' => 'External Course',
        'source_credit_hours' => '3.00',
        'external_grade' => 'A',
        'document_ids' => [waiverTranscriptId()],
        'normalized_grade' => 'A',
        'verified_by_user_id' => $head->id,
        'verified_at' => now(),
        'status' => TransferRequestStatus::Approved,
        'revision' => 1,
        'submitted_at' => now(),
    ]);
    TransferDecision::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $target->id,
        'target_course_code' => $target->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => $target->total_ch,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-511',
        'external_course_name' => 'External Course',
        'source_credit_hours' => '3.00',
        'external_grade' => 'A',
        'document_ids' => [waiverTranscriptId()],
        'normalized_grade' => 'A',
        'verified_by_user_id' => $head->id,
        'verified_at' => now(),
        'approved_by_user_id' => $council->id,
        'approved_at' => now(),
        'transfer_request_id' => $transferRequest->id,
    ]);
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/credit-decisions/waiver-requests/targets')
        ->assertOk()->assertJsonPath('data.0.already_recognized', true);
    $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => ['source' => 'prs', 'institution' => 'E-JUST', 'enrollment_id' => $priorEnrollment->id],
    ])->assertConflict();
});

it('offers bound Curriculum targets and only qualifying PRS prior E-JUST results', function () {
    [$student, , , , $target, $prior, $priorEnrollment] = waiverWorld();
    $unearnedOffering = CourseOffering::factory()->create(['course_id' => $prior->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id, 'course_offering_id' => $unearnedOffering->id,
        'classification' => EnrollmentClassification::Core, 'earned' => false,
    ]);
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/credit-decisions/waiver-requests/targets')
        ->assertOk()->assertJsonPath('data.0.target_course_id', $target->id)
        ->assertJsonPath('data.0.credit_hours', 3);
    $this->getJson('/api/v1/credit-decisions/waiver-requests/prior-results')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.enrollment_id', $priorEnrollment->id)
        ->assertJsonPath('data.0.course.level', 400)
        ->assertJsonPath('data.0.minimum_result', 'C+');
});

it('returns revised evidence to fresh Advisor and Head review while retaining immutable verification history', function () {
    [$student, $advisor, $head, $council, $target, , $priorEnrollment] = waiverWorld();
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => ['source' => 'prs', 'institution' => 'E-JUST', 'enrollment_id' => $priorEnrollment->id],
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '3873a57e-aef4-4e5c-a615-f8575c9a76ef',
    ])->assertOk();
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => 'af355020-a147-48b8-91bb-b78b9bd5a43b',
    ])->assertOk();
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'coverage_percentage' => 79.99, 'equivalency_note' => 'Almost equivalent.',
        'expected_revision' => 1, 'action_id' => '25677165-c5f5-4799-8a4f-afc844013d8f',
    ])->assertConflict()->assertJsonPath('message', 'Verified content coverage must be at least 80%.');
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'coverage_percentage' => 86.5, 'equivalency_note' => 'Verified against both outlines.',
        'expected_revision' => 1, 'action_id' => '1214c61e-ce63-4d5e-a61a-4eec8d18a8c1',
    ])->assertOk();

    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/council-decision", [
        'action' => 'return', 'reason' => 'Attach the signed transcript page.',
        'expected_revision' => 1, 'action_id' => '5c162f6c-fc1a-4ca5-974e-0a34eb6e56f3',
    ])->assertOk()->assertJsonPath('data.status', 'Returned');

    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '99dc0125-f7ba-43d6-81df-5f7dbcedd420',
    ])->assertConflict();
    $updated = $this->patchJson("/api/v1/credit-decisions/waiver-requests/{$id}", [
        'expected_revision' => 1,
        'student_note' => 'Signed transcript page attached.',
    ])->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.head_verification', null);
    expect($updated->json('data.transitions.3.verification_snapshot.coverage_percentage'))->toBe(86.5)
        ->and($updated->json('data.transitions.4.verification_snapshot.coverage_percentage'))->toBe(86.5);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 2, 'action_id' => 'a83686cc-a102-4142-8481-14f90bcf4f4f',
    ])->assertOk()->assertJsonPath('data.status', 'PendingAdvisor');
});

it('provides actor-scoped paginated queues and formula-safe audited exports at every review stage', function () {
    [$student, $advisor, $head, $council, $target, , $priorEnrollment] = waiverWorld();
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => ['source' => 'prs', 'institution' => 'E-JUST', 'enrollment_id' => $priorEnrollment->id],
        'student_note' => '=HYPERLINK("https://example.test")',
    ])->assertCreated()->json('data.id');

    $this->getJson('/api/v1/credit-decisions/waiver-requests?per_page=1')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
    $download = $this->get('/api/v1/credit-decisions/waiver-requests/export')
        ->assertOk()->assertDownload('waiver-requests.xlsx');
    $reader = new Reader;
    $reader->open($download->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows[1])->toContain('=HYPERLINK("https://example.test")')
        ->and(AuditLog::query()->where('action', 'waiver.requests.exported')->latest('id')->firstOrFail()->metadata['row_count'])->toBe(1);

    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '783fe89d-a1ce-4f22-bc6b-ad205aa855fb',
    ])->assertOk();
    Sanctum::actingAs($advisor);
    $this->getJson('/api/v1/credit-decisions/waiver-requests/advisor-queue?q='.$target->code)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.student.university_id', $student->university_id);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '4388f596-dfdd-4c68-9219-fc153c45ecf8',
    ])->assertOk();

    Sanctum::actingAs($head);
    $this->getJson('/api/v1/credit-decisions/waiver-requests/department-head-queue')
        ->assertOk()->assertJsonPath('meta.total', 1);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'coverage_percentage' => 80, 'equivalency_note' => 'Equivalent.',
        'expected_revision' => 1, 'action_id' => 'f0a72e5f-722c-42f8-ab47-f139316964ed',
    ])->assertOk();

    Sanctum::actingAs($council);
    $this->getJson('/api/v1/credit-decisions/waiver-requests/council-queue')
        ->assertOk()->assertJsonPath('meta.total', 1);
});

it('blocks Waiver after an effective failed or earned target Attempt without mutating academic records', function (bool $earned) {
    [$student, , , , $target, , $priorEnrollment] = waiverWorld();
    $targetOffering = CourseOffering::factory()->create(['course_id' => $target->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $targetOffering->id,
        'classification' => EnrollmentClassification::Core,
        'earned' => $earned,
    ]);
    Sanctum::actingAs($student->user);

    $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => ['source' => 'prs', 'institution' => 'E-JUST', 'enrollment_id' => $priorEnrollment->id],
    ])->assertConflict()->assertJsonPath('message', 'A Waiver cannot target a Course with an effective for-credit Attempt.');

    $this->assertDatabaseCount('waiver_requests', 0);
    $this->assertDatabaseCount('enrollments', 2);
})->with(['failed' => false, 'earned' => true]);

it('revalidates target Attempt eligibility before Council approval', function () {
    [$student, $advisor, $head, $council, $target, , $priorEnrollment] = waiverWorld();
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => ['source' => 'prs', 'institution' => 'E-JUST', 'enrollment_id' => $priorEnrollment->id],
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '13eb721d-4fba-4eb0-a54a-d09565336595',
    ])->assertOk();
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '16c57e4c-bddd-4daa-842d-c1f269de849d',
    ])->assertOk();
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", [
        'action' => 'verify', 'coverage_percentage' => 80, 'equivalency_note' => 'Equivalent.',
        'expected_revision' => 1, 'action_id' => 'a6ad09d6-57f6-44e2-a9cf-85da8f851f87',
    ])->assertOk();

    $targetOffering = CourseOffering::factory()->create(['course_id' => $target->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $targetOffering->id,
        'classification' => EnrollmentClassification::Core,
        'earned' => false,
    ]);

    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/council-decision", [
        'action' => 'approve', 'expected_revision' => 1,
        'action_id' => 'f7592a34-7bd7-4217-bb33-74bf7268c104',
    ])->assertConflict()->assertJsonPath('message', 'A Waiver cannot target a Course with an effective for-credit Attempt.');
    $this->assertDatabaseCount('waiver_decisions', 0);
});

it('revalidates live role scope lifecycle revision and replay authorization on every mutation', function () {
    [$student, $advisor, $head, , $target, , $priorEnrollment] = waiverWorld();
    $replacementAdvisor = User::factory()->create();
    $replacementAdvisor->assignRole(Role::Advisor);
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => ['source' => 'prs', 'institution' => 'E-JUST', 'enrollment_id' => $priorEnrollment->id],
    ])->assertCreated()->json('data.id');
    $this->patchJson("/api/v1/credit-decisions/waiver-requests/{$id}", [
        'expected_revision' => 99, 'student_note' => 'stale',
    ])->assertConflict();
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '71394d76-124b-4c83-9873-914630faf419',
    ])->assertOk();

    $advisorAction = ['action' => 'recommend', 'expected_revision' => 1, 'action_id' => '66931e47-4619-4bd2-aaeb-dce440be4840'];
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/advisor-decision", $advisorAction)->assertOk();
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/advisor-decision", $advisorAction)->assertOk();
    $student->update(['advisor_id' => $replacementAdvisor->id]);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/advisor-decision", $advisorAction)->assertForbidden();

    $headAction = [
        'action' => 'verify', 'coverage_percentage' => 80, 'equivalency_note' => 'Equivalent.',
        'expected_revision' => 1, 'action_id' => '9a0748bd-109d-48fe-8301-a92c52f94c20',
    ];
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", $headAction)->assertOk();
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", $headAction)->assertOk();
    DepartmentHeadAssignment::query()->where('user_id', $head->id)->delete();
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$id}/department-head-decision", $headAction)->assertForbidden();

    Sanctum::actingAs($student->user);
    $draft = $this->postJson('/api/v1/credit-decisions/waiver-requests', [
        'target_course_id' => $target->id,
        'prior_result' => ['source' => 'prs', 'institution' => 'E-JUST', 'enrollment_id' => $priorEnrollment->id],
    ])->assertCreated()->json('data.id');
    $student->update(['lifecycle_status' => 'Terminated']);
    $this->postJson("/api/v1/credit-decisions/waiver-requests/{$draft}/submit", [
        'expected_revision' => 1, 'action_id' => '077515cf-0789-4a20-8166-fbb0124820a9',
    ])->assertConflict()->assertJsonPath('message', 'A terminated Student cannot change a Waiver request.');
});
