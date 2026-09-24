<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\AcademicStanding\Public\TerminationCaseSummary;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Public\Contracts\Notifier;

uses(RefreshDatabase::class);

it('opens one deterministic case for effective D or F grades in two distinct courses and clears it when evidence clears', function () {
    $student = Student::factory()->create();
    $grading = Mockery::mock(GradingRecords::class);
    $attempts = [
        new EffectiveAttemptSummary(11, 101, 'Core', 3, false, 'D'),
        new EffectiveAttemptSummary(12, 102, 'Core', 3, false, 'F'),
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
        ->and($cases->reconcile($student->id))->toBeNull();
    $this->assertDatabaseHas('termination_cases', ['id' => $opened?->id, 'state' => 'TriggerCleared']);
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
});
