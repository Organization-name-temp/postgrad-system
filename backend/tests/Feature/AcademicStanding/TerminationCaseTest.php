<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\AcademicStanding\Public\TerminationCaseSummary;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Public\Contracts\Notifier;

uses(RefreshDatabase::class);

/** @return array{Student, Course, Semester} */
function ordinaryCheckpointWorld(): array
{
    $program = Program::factory()->create();
    $course = Course::factory()->create(['total_ch' => 1]);
    $version = CatalogVersion::query()->create(['subject_type' => VersionSubject::Program, 'subject_id' => $program->id, 'version_no' => 1, 'snapshot' => ['prep_required_ch' => 1, 'core_required_ch' => 0, 'elective_required_ch' => 0, 'curriculum' => [['course_id' => $course->id, 'course_code' => $course->code, 'course_total_ch' => 1, 'category' => CourseCategory::Preparatory->value, 'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage->value, 'ordinal' => 0]]], 'changed_at' => now()]);
    $first = Semester::factory()->create(['term' => Term::Fall, 'year' => 2024, 'registration_opens_at' => now()->subYears(2)->subMonth(), 'registration_closes_at' => now()->subYears(2)->subWeeks(3), 'starts_at' => now()->subYears(2), 'add_drop_ends_at' => now()->subYears(2)->addWeeks(2), 'late_add_ends_at' => now()->subYears(2)->addWeeks(3), 'withdrawal_deadline_at' => now()->subYears(2)->addMonths(2), 'ends_at' => now()->subYears(2)->addMonths(4), 'grade_submission_deadline_at' => now()->subYears(2)->addMonths(5)]);
    $second = Semester::factory()->create(['term' => Term::Spring, 'year' => 2025, 'registration_opens_at' => now()->subMonths(19), 'registration_closes_at' => now()->subMonths(18)->subWeek(), 'starts_at' => now()->subMonths(18), 'add_drop_ends_at' => now()->subMonths(18)->addWeeks(2), 'late_add_ends_at' => now()->subMonths(18)->addWeeks(3), 'withdrawal_deadline_at' => now()->subMonths(16), 'ends_at' => now()->subMonths(14), 'grade_submission_deadline_at' => now()->subMonths(13)]);
    $student = Student::factory()->create(['program_id' => $program->id, 'curriculum_version_id' => $version->id, 'enrollment_term' => $first->name_en]);

    return [$student, $course, $second];
}

it('opens one deterministic case for effective D or F grades in two distinct courses and clears it when evidence clears', function () {
    $student = Student::factory()->create();
    $grading = Mockery::mock(GradingRecords::class);
    $attempts = [
        new EffectiveAttemptSummary(21, 11, 101, 'Core', 3, false, 'D'),
        new EffectiveAttemptSummary(22, 12, 102, 'Core', 3, false, 'F'),
    ];
    $grading->shouldReceive('effectiveAttemptsForStudent')->twice()->andReturn($attempts, []);
    app()->instance(GradingRecords::class, $grading);
    $remediation = Mockery::mock(RemediationObligations::class);
    $remediation->shouldReceive('forStudent')->twice()->andReturn([]);
    app()->instance(RemediationObligations::class, $remediation);
    app()->instance(Notifier::class, Mockery::mock(Notifier::class)->shouldIgnoreMissing());
    $cases = app(TerminationCases::class);

    $opened = $cases->reconcile($student->id);
    expect($opened?->state)->toBe('AwaitingAdvisor')
        ->and($opened?->evidence['df_grades'][0]['grade_id'])->toBe(21)
        ->and($opened?->evidence['df_grades'][1]['grade_id'])->toBe(22)
        ->and($cases->reconcile($student->id))->toBeNull();
    $this->assertDatabaseHas('termination_cases', ['id' => $opened?->id, 'state' => 'TriggerCleared']);
});

it('opens ordinary Preparatory noncompletion only after the second main Semester grade deadline', function () {
    [$student, $course] = ordinaryCheckpointWorld();
    $grading = Mockery::mock(GradingRecords::class);
    $grading->shouldReceive('effectiveAttemptsForStudent')->once()->andReturn([]);
    app()->instance(GradingRecords::class, $grading);
    app()->instance(Notifier::class, Mockery::mock(Notifier::class)->shouldIgnoreMissing());

    $case = app(TerminationCases::class)->reconcile($student->id);

    $component = $case?->evidence['preparatory_noncompletion'][0];
    expect($component['course_id'])->toBe($course->id)
        ->and($component['requirement_id'])->toContain(':'.$course->id)
        ->and($component['checkpoint']['source'])->toBe('ordinary')
        ->and($component['effective_grade'])->toBe(['kind' => 'no_attempt'])
        ->and($component['withdrawal_enrollment_ids'])->toBe([])
        ->and($component['effective_exemption_ids'])->toBe([])
        ->and($component['revocation_ids'])->toBe([]);
});

it('defers ordinary Preparatory noncompletion while the affected Enrollment is Incomplete', function () {
    [$student, $course, $second] = ordinaryCheckpointWorld();
    $offering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $second->id]);
    Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $offering->id, 'classification' => EnrollmentClassification::Preparatory, 'course_status' => CourseStatus::Incomplete, 'credit_hours' => 1, 'earned' => null]);
    $grading = Mockery::mock(GradingRecords::class);
    $grading->shouldReceive('effectiveAttemptsForStudent')->once()->andReturn([]);
    app()->instance(GradingRecords::class, $grading);
    app()->instance(Notifier::class, Mockery::mock(Notifier::class)->shouldIgnoreMissing());

    expect(app(TerminationCases::class)->reconcile($student->id))->toBeNull();
    $this->assertDatabaseCount('termination_cases', 0);
});

it('lets Council atomically terminate the student and reject open registration requests', function () {
    $student = Student::factory()->create();
    $council = User::factory()->create();
    $council->assignRole(Role::Council);
    $case = TerminationCase::query()->create(['student_id' => $student->id, 'fingerprint' => str_repeat('a', 64), 'state' => 'AwaitingCouncil', 'evidence' => ['df_grades' => [['course_id' => 1], ['course_id' => 2]]]]);
    $registration = RegistrationRequest::factory()->create(['student_id' => $student->id, 'status' => RegistrationStatus::Pending]);
    $cases = Mockery::mock(TerminationCases::class)->shouldIgnoreMissing();
    $cases->shouldReceive('reconcile')->once()->with($student->id)->andReturn(new TerminationCaseSummary($case->id, $student->id, $case->fingerprint, 'AwaitingCouncil', $case->evidence));
    $cases->shouldReceive('currentForStudent')->andReturnNull();
    app()->instance(TerminationCases::class, $cases);
    $grading = Mockery::mock(GradingRecords::class);
    $grading->shouldReceive('cgpaFor')->andReturnNull();
    $grading->shouldReceive('effectiveAttemptsForStudent')->andReturn([]);
    app()->instance(GradingRecords::class, $grading);
    app()->instance(Notifier::class, Mockery::mock(Notifier::class)->shouldIgnoreMissing());
    Sanctum::actingAs($council);

    $this->postJson("/api/v1/termination-cases/{$case->id}/council-decision", ['action' => 'terminate', 'reason' => 'Council confirmed the evidence.'])
        ->assertOk()->assertJsonPath('data.state', 'Terminated');
    $this->assertDatabaseHas('students', ['id' => $student->id, 'lifecycle_status' => 'Terminated']);
    $this->assertDatabaseHas('registration_requests', ['id' => $registration->id, 'status' => 'Rejected']);
    $this->assertDatabaseHas('academic_request_closeouts', ['student_id' => $student->id, 'request_type' => 'registration', 'request_id' => $registration->id, 'termination_case_id' => $case->id]);
    $this->assertDatabaseHas('registration_request_transitions', ['registration_request_id' => $registration->id, 'action' => 'system_termination_closeout', 'system_cause_type' => 'termination_case', 'system_cause_id' => $case->id]);
});

it('preserves the final attributed case outcome for the student', function () {
    $student = Student::factory()->create();
    $student->user->assignRole(Role::Student);
    TerminationCase::query()->create([
        'student_id' => $student->id,
        'fingerprint' => str_repeat('b', 64),
        'state' => 'Dismissed',
        'evidence' => ['df_grades' => []],
        'advisor_assessment' => 'Reviewed by advisor.',
        'department_head_reason' => 'Evidence no longer actionable.',
        'closed_reason' => 'Dismissed',
        'closed_at' => now(),
    ]);
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/termination-cases/mine')
        ->assertOk()
        ->assertJsonPath('data.state', 'Dismissed')
        ->assertJsonPath('data.advisor_assessment', 'Reviewed by advisor.')
        ->assertJsonPath('data.department_head_reason', 'Evidence no longer actionable.');
});

it('paginates preserved Council history and audits its live queue export', function () {
    $council = User::factory()->create();
    $council->assignRole(Role::Council);
    $student = Student::factory()->create();
    TerminationCase::query()->create(['student_id' => $student->id, 'fingerprint' => str_repeat('c', 64), 'state' => 'AwaitingCouncil', 'evidence' => ['df_grades' => []]]);
    TerminationCase::query()->create(['student_id' => $student->id, 'fingerprint' => str_repeat('d', 64), 'state' => 'Dismissed', 'evidence' => ['df_grades' => []], 'closed_reason' => 'Dismissed', 'closed_at' => now()]);
    Sanctum::actingAs($council);

    $this->getJson('/api/v1/termination-cases/council?include_decided=true&per_page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    $this->get('/api/v1/termination-cases/council/export')
        ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $this->assertDatabaseHas('audit_log', ['action' => 'termination.cases.exported', 'actor_user_id' => $council->id]);
});
