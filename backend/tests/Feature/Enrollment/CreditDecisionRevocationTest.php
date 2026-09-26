<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Admissions\Public\AdmissionDocumentSummary;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\CreditDecisionType;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\ExemptionSource;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Public\Events\GradeOutcomesFinalized;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Notifications\Internal\Models\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

uses(RefreshDatabase::class);

/** @return array{Student, User, Course, TransferDecision} */
function revocationWorld(): array
{
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $course = Course::factory()->create([
        'department_id' => $department->id,
        'code' => 'REV-501',
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
    $student = Student::factory()->create([
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
    ]);
    $student->user->assignRole(Role::Student);
    $council = User::factory()->create();
    $council->assignRole(Role::Council);
    $request = TransferRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $version->id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => 3,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-501',
        'external_course_name' => 'External Course',
        'source_credit_hours' => '3.00',
        'external_grade' => 'A',
        'document_ids' => [],
        'normalized_grade' => 'A',
        'verified_by_user_id' => $council->id,
        'verified_at' => now(),
        'status' => TransferRequestStatus::Approved,
        'revision' => 1,
        'submitted_at' => now(),
    ]);
    $decision = TransferDecision::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $version->id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => 3,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-501',
        'external_course_name' => 'External Course',
        'source_credit_hours' => '3.00',
        'external_grade' => 'A',
        'document_ids' => [],
        'normalized_grade' => 'A',
        'verified_by_user_id' => $council->id,
        'verified_at' => now(),
        'approved_by_user_id' => $council->id,
        'approved_at' => now(),
        'transfer_request_id' => $request->id,
    ]);

    return [$student, $council, $course, $decision];
}

function endSeedTransferDecision(TransferDecision $decision): void
{
    CreditDecisionRevocation::query()->create([
        'decision_type' => CreditDecisionType::Transfer,
        'decision_id' => $decision->id,
        'student_id' => $decision->student_id,
        'target_course_id' => $decision->target_course_id,
        'revoked_by_user_id' => $decision->approved_by_user_id,
        'reason' => 'Test setup correction.',
        'created_at' => now(),
    ]);
}

it('lets Council append a reasoned Transfer revocation without rewriting the decision', function () {
    [$student, $council, , $decision] = revocationWorld();
    Sanctum::actingAs($council);

    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", [
        'reason' => 'The source institution corrected its transcript.',
    ])->assertOk()
        ->assertJsonPath('data.id', $decision->id)
        ->assertJsonPath('data.effective', false)
        ->assertJsonPath('data.revocation.reason', 'The source institution corrected its transcript.')
        ->assertJsonPath('data.revocation.revoked_by_user_id', $council->id);

    expect(TransferDecision::query()->findOrFail($decision->id)->approved_at->equalTo($decision->approved_at))->toBeTrue()
        ->and(app(TransferDecisions::class)->effectiveForStudent($student->id))->toBeEmpty();
    $this->assertDatabaseHas('credit_decision_revocations', [
        'decision_type' => 'transfer',
        'decision_id' => $decision->id,
        'student_id' => $student->id,
        'reason' => 'The source institution corrected its transcript.',
    ]);
    $this->getJson('/api/v1/credit-decisions/transfer-requests/council-queue?include_decided=true')
        ->assertOk()
        ->assertJsonPath('data.0.decision.effective', false);
});

it('requires Council authority and a reason and never appends a second revocation', function () {
    [, $council, , $decision] = revocationWorld();
    $outsider = User::factory()->create();
    $outsider->assignRole(Role::Advisor);

    Sanctum::actingAs($outsider);
    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", ['reason' => 'No authority'])
        ->assertForbidden();

    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", ['reason' => ''])
        ->assertUnprocessable();
    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", ['reason' => 'Correction'])
        ->assertOk();
    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", ['reason' => 'Second correction'])
        ->assertConflict();

    $this->assertDatabaseCount('credit_decision_revocations', 1);
});

it('freezes effective credit decisions after the student is terminated', function () {
    [$student, $council, , $decision] = revocationWorld();
    $student->update(['lifecycle_status' => StudentLifecycleStatus::Terminated]);
    Sanctum::actingAs($council);

    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", ['reason' => 'Late correction.'])
        ->assertConflict();

    $this->assertDatabaseCount('credit_decision_revocations', 0);
});

it('lets only the scoped Department Head revoke an Exemption decision', function () {
    [$student, , $course] = revocationWorld();
    $first = Semester::factory()->create(['term' => Term::Fall, 'year' => 2024, 'registration_opens_at' => now()->subYears(2)->subMonth(), 'registration_closes_at' => now()->subYears(2)->subWeeks(3), 'starts_at' => now()->subYears(2), 'add_drop_ends_at' => now()->subYears(2)->addWeeks(2), 'late_add_ends_at' => now()->subYears(2)->addWeeks(3), 'withdrawal_deadline_at' => now()->subYears(2)->addMonths(2), 'ends_at' => now()->subYears(2)->addMonths(4), 'grade_submission_deadline_at' => now()->subYears(2)->addMonths(5)]);
    Semester::factory()->create(['term' => Term::Spring, 'year' => 2025, 'registration_opens_at' => now()->subMonths(19), 'registration_closes_at' => now()->subMonths(18)->subWeek(), 'starts_at' => now()->subMonths(18), 'add_drop_ends_at' => now()->subMonths(18)->addWeeks(2), 'late_add_ends_at' => now()->subMonths(18)->addWeeks(3), 'withdrawal_deadline_at' => now()->subMonths(16), 'ends_at' => now()->subMonths(14), 'grade_submission_deadline_at' => now()->subMonths(13)]);
    $student->update(['enrollment_term' => $first->name_en]);
    $decision = ExemptionDecision::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage,
        'credit_hours' => 3,
        'source' => ExemptionSource::Automatic,
        'reason_codes' => ['qualifying_score'],
        'source_evidence' => ['score' => 90],
        'admission_application_id' => 901,
        'approved_at' => now(),
    ]);
    $outsideDepartment = Department::factory()->create();
    $outsideHead = User::factory()->create();
    $outsideHead->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create([
        'user_id' => $outsideHead->id,
        'department_id' => $outsideDepartment->id,
    ]);
    Sanctum::actingAs($outsideHead);
    $this->postJson("/api/v1/credit-decisions/exemptions/{$decision->id}/revoke", [
        'reason' => 'Outside scope.',
    ])->assertForbidden();

    $scopedHead = User::factory()->create();
    $scopedHead->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create([
        'user_id' => $scopedHead->id,
        'department_id' => $course->department_id,
    ]);
    Sanctum::actingAs($scopedHead);
    $this->postJson("/api/v1/credit-decisions/exemptions/{$decision->id}/revoke", [
        'reason' => 'Admission evidence was corrected.',
    ])->assertOk()
        ->assertJsonPath('data.effective', false)
        ->assertJsonPath('data.revocation.revoked_by_user_id', $scopedHead->id);
    $this->assertDatabaseHas('remediation_obligations', [
        'student_id' => $student->id,
        'course_id' => $course->id,
        'state' => 'AwaitingOffering',
    ]);
    $this->getJson('/api/v1/credit-decisions/exemption-requests/department-head-queue?include_decided=true')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('does not create remediation when an Exemption is revoked before the ordinary checkpoint', function () {
    [$student, , $course] = revocationWorld();
    $first = Semester::factory()->create(['term' => Term::Fall, 'year' => 2026]);
    Semester::factory()->create(['term' => Term::Spring, 'year' => 2027, 'starts_at' => now()->addMonths(6), 'registration_opens_at' => now()->addMonths(5), 'registration_closes_at' => now()->addMonths(5)->addWeek(), 'add_drop_ends_at' => now()->addMonths(6)->addWeeks(2), 'late_add_ends_at' => now()->addMonths(6)->addWeeks(3), 'withdrawal_deadline_at' => now()->addMonths(8), 'ends_at' => now()->addMonths(10), 'grade_submission_deadline_at' => now()->addMonths(11)]);
    $student->update(['enrollment_term' => $first->name_en]);
    $decision = ExemptionDecision::query()->create([
        'student_id' => $student->id, 'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id, 'target_course_code' => $course->code,
        'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage,
        'credit_hours' => 3, 'source' => ExemptionSource::Automatic, 'reason_codes' => ['qualifying_score'],
        'source_evidence' => ['score' => 90], 'admission_application_id' => 902, 'approved_at' => now(),
    ]);
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create(['user_id' => $head->id, 'department_id' => $course->department_id]);
    Sanctum::actingAs($head);

    $this->postJson("/api/v1/credit-decisions/exemptions/{$decision->id}/revoke", ['reason' => 'Corrected before the checkpoint.'])->assertOk();

    $this->assertDatabaseCount('remediation_obligations', 0);
});

it('lets Council revoke a Waiver decision while preserving its request and evidence', function () {
    [$student, $council, $course] = revocationWorld();
    $priorCourse = Course::factory()->create([
        'department_id' => $course->department_id,
        'code' => 'REV-401',
    ]);
    $request = WaiverRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'credit_hours' => 3,
        'prior_course_id' => $priorCourse->id,
        'prior_course_code' => $priorCourse->code,
        'prior_course_level' => 400,
        'prior_result_source' => 'legacy_transcript',
        'prior_letter_grade' => 'A',
        'evidence' => ['document_ids' => ['immutable-document']],
        'status' => WaiverRequestStatus::Approved,
        'revision' => 1,
        'submitted_at' => now(),
        'coverage_percentage' => 90,
        'equivalency_note' => 'Originally verified.',
        'verified_by' => $council->id,
        'verified_at' => now(),
    ]);
    $decision = WaiverDecision::query()->create([
        'waiver_request_id' => $request->id,
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'credit_hours' => 3,
        'prior_course_id' => $priorCourse->id,
        'prior_course_code' => $priorCourse->code,
        'prior_course_level' => 400,
        'prior_result_source' => 'legacy_transcript',
        'prior_letter_grade' => 'A',
        'coverage_percentage' => 90,
        'verified_by' => $council->id,
        'verified_at' => now(),
        'approved_by' => $council->id,
        'approved_at' => now(),
    ]);

    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/waivers/{$decision->id}/revoke", [
        'reason' => 'Coverage verification was corrected.',
    ])->assertOk()->assertJsonPath('data.effective', false);

    WaiverRequest::query()->create([
        ...$request->only([
            'student_id', 'curriculum_version_id', 'target_course_id', 'target_course_code',
            'credit_hours', 'prior_course_id', 'prior_course_code', 'prior_course_level',
            'prior_result_source', 'prior_letter_grade', 'evidence', 'submitted_at',
        ]),
        'status' => WaiverRequestStatus::Rejected,
        'revision' => 1,
    ]);
    $this->getJson('/api/v1/credit-decisions/waiver-requests/council-queue?include_decided=true')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($request->fresh()->evidence)->toBe(['document_ids' => ['immutable-document']])
        ->and($request->fresh()->status)->toBe(WaiverRequestStatus::Approved)
        ->and(WaiverDecision::query()->find($decision->id))->not->toBeNull();
});

it('idempotently rejects an in-review Transfer when a failed Grade becomes effective', function () {
    [$student, , $course, $decision] = revocationWorld();
    endSeedTransferDecision($decision);
    $request = TransferRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => 3,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-FAIL',
        'external_course_name' => 'Failed target',
        'source_credit_hours' => '3.00',
        'external_grade' => 'B',
        'document_ids' => [],
        'status' => TransferRequestStatus::PendingAdvisor,
        'revision' => 1,
        'submitted_at' => now(),
    ]);
    $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'earned' => false,
    ]);

    Event::dispatch(new GradeOutcomesFinalized(77, [$enrollment->id]));
    Event::dispatch(new GradeOutcomesFinalized(77, [$enrollment->id]));

    expect($request->fresh()->status)->toBe(TransferRequestStatus::Rejected)
        ->and($request->transitions()->where('action', 'system_rejected_failed_attempt')->count())->toBe(1);
    $transition = $request->transitions()->where('action', 'system_rejected_failed_attempt')->firstOrFail();
    expect($transition->actor_user_id)->toBeNull()
        ->and($transition->system_cause_type)->toBe('grade_sheet')
        ->and($transition->system_cause_id)->toBe(77)
        ->and(Notification::query()->where('user_id', $student->user_id)->where('type', 'transfer.request_decided')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $student->advisor_id)->where('type', 'transfer.request_decided')->count())->toBe(1);
});

it('rejects Grade finalization when a conflicting Transfer approval won the Student lock first', function () {
    [$student, , $course, $decision] = revocationWorld();
    $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'earned' => false,
    ]);

    expect(fn () => Event::dispatch(new GradeOutcomesFinalized(79, [$enrollment->id])))
        ->toThrow(ConflictHttpException::class);

    expect(app(TransferDecisions::class)->effectiveForStudent($student->id))->toHaveCount(1)
        ->and($decision->fresh()->revocation)->toBeNull()
        ->and(Notification::query()
            ->where('user_id', $student->user_id)
            ->where('type', 'credit_decision.revoked')
            ->count())->toBe(0);
});

it('idempotently rejects an in-review Waiver and notifies its current Council reviewers', function () {
    [$student, $council, $course, $decision] = revocationWorld();
    endSeedTransferDecision($decision);
    $priorCourse = Course::factory()->create([
        'department_id' => $course->department_id,
        'code' => 'REV-402',
    ]);
    $request = WaiverRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'credit_hours' => 3,
        'prior_course_id' => $priorCourse->id,
        'prior_course_code' => $priorCourse->code,
        'prior_course_level' => 400,
        'prior_result_source' => 'legacy_transcript',
        'prior_letter_grade' => 'A',
        'evidence' => ['document_ids' => ['immutable-document']],
        'status' => WaiverRequestStatus::PendingCouncil,
        'revision' => 1,
        'submitted_at' => now(),
        'coverage_percentage' => 90,
        'equivalency_note' => 'Verified.',
        'verified_by' => $council->id,
        'verified_at' => now(),
    ]);
    $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'earned' => false,
    ]);

    Event::dispatch(new GradeOutcomesFinalized(78, [$enrollment->id]));
    Event::dispatch(new GradeOutcomesFinalized(78, [$enrollment->id]));

    expect($request->fresh()->status)->toBe(WaiverRequestStatus::Rejected)
        ->and($request->transitions()->where('action', 'system_rejected_failed_attempt')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $student->user_id)->where('type', 'waiver.request_decided')->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $council->id)->where('type', 'waiver.request_decided')->count())->toBe(1);
});

it('frees Transfer hours and requires a replacement to complete a new workflow', function () {
    [$student, $council, $course, $decision] = revocationWorld();
    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", [
        'reason' => 'Corrected source record.',
    ])->assertOk();
    $replacement = TransferRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => 3,
        'external_institution' => 'Replacement University',
        'external_course_code' => 'EXT-NEW',
        'external_course_name' => 'Replacement evidence',
        'source_credit_hours' => '4.00',
        'external_grade' => 'Excellent',
        'document_ids' => [],
        'normalized_grade' => 'A',
        'verified_by_user_id' => $council->id,
        'verified_at' => now(),
        'status' => TransferRequestStatus::PendingCouncil,
        'revision' => 1,
        'submitted_at' => now(),
    ]);

    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$replacement->id}/council-decision", [
        'action' => 'approve',
        'expected_revision' => 1,
        'action_id' => '71717171-7171-4171-8171-717171717171',
    ])->assertOk()->assertJsonPath('data.status', 'Approved');

    expect(TransferDecision::query()->where('student_id', $student->id)->count())->toBe(2)
        ->and(app(TransferDecisions::class)->effectiveForStudent($student->id))->toHaveCount(1)
        ->and(app(TransferDecisions::class)->effectiveForStudent($student->id)[0]->externalInstitution)
        ->toBe('Replacement University');
});

it('rejects a new Transfer submission after any effective completed Attempt', function (bool $earned) {
    [$student, $council, $course, $decision] = revocationWorld();
    Sanctum::actingAs($council);
    $this->postJson("/api/v1/credit-decisions/transfers/{$decision->id}/revoke", [
        'reason' => 'Corrected source record.',
    ])->assertOk();
    $documentId = '01ARZ3NDEKTSV4RRFFQ69G5FAY';
    app()->instance(AdmissionDocuments::class, new class($documentId) implements AdmissionDocuments
    {
        public function __construct(private readonly string $documentId) {}

        public function forApplicant(int $userId): array
        {
            return [new AdmissionDocumentSummary($this->documentId, 'transcript', 'transcript.pdf')];
        }

        public function downloadForApplicant(int $userId, string $documentId): ?StreamedResponse
        {
            return null;
        }
    });
    $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'earned' => $earned,
    ]);
    Sanctum::actingAs($student->user);
    $draftId = $this->postJson('/api/v1/credit-decisions/transfer-requests', [
        'target_course_id' => $course->id,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-FAIL',
        'external_course_name' => 'Failed target',
        'source_credit_hours' => 3,
        'external_grade' => 'B',
        'document_ids' => [$documentId],
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/credit-decisions/transfer-requests/{$draftId}/submit", [
        'expected_revision' => 1,
        'action_id' => '72727272-7272-4272-8272-727272727272',
    ])->assertConflict()->assertJsonPath('message', __('credit_decisions.transfer.target_attempt_exists'));
})->with([false, true]);
