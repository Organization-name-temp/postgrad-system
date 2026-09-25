<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\Events\CourseChanged;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\CreditDecisionType;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Identity\Internal\Models\Student;

uses(RefreshDatabase::class);

it('activates a remediation obligation at the earliest future offering and keeps its deadline monotonic', function () {
    $student = Student::factory()->create();
    $course = Course::factory()->create();
    $semester = Semester::factory()->create([
        'starts_at' => now()->addMonth(),
        'late_add_ends_at' => now()->addMonths(2),
        'grade_submission_deadline_at' => now()->addMonths(5),
        'ends_at' => now()->addMonths(4),
    ]);
    $offering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id]);
    $revocation = CreditDecisionRevocation::query()->create([
        'decision_type' => CreditDecisionType::Exemption,
        'decision_id' => 99,
        'student_id' => $student->id,
        'target_course_id' => $course->id,
        'revoked_by_user_id' => null,
        'reason' => 'corrected evidence',
        'created_at' => now(),
    ]);

    $summary = app(RemediationObligations::class)->createFromRevocation($revocation->id);
    expect($summary->state)->toBe('Active')
        ->and($summary->opportunityOfferingId)->toBe($offering->id)
        ->and($summary->checkpointSemesterId)->toBe($semester->id);
    $this->assertDatabaseCount('remediation_obligation_transitions', 1);
    app(RemediationObligations::class)->reconcileStudent($student->id);
    $this->assertDatabaseCount('remediation_obligation_transitions', 1);
});

it('rereads course changes and reactivates an awaiting obligation idempotently', function () {
    $student = Student::factory()->create();
    $course = Course::factory()->create(['is_active' => true]);
    $semester = Semester::factory()->create([
        'starts_at' => now()->addMonth(),
        'late_add_ends_at' => now()->addMonths(2),
        'grade_submission_deadline_at' => now()->addMonths(5),
        'ends_at' => now()->addMonths(4),
    ]);
    CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id]);
    $revocation = CreditDecisionRevocation::query()->create([
        'decision_type' => CreditDecisionType::Exemption,
        'decision_id' => 100,
        'student_id' => $student->id,
        'target_course_id' => $course->id,
        'revoked_by_user_id' => null,
        'reason' => 'corrected evidence',
        'created_at' => now(),
    ]);
    $obligations = app(RemediationObligations::class);
    $obligations->createFromRevocation($revocation->id);

    $course->update(['is_active' => false]);
    event(new CourseChanged($course->id));
    expect($obligations->forStudent($student->id)[0]->state)->toBe('AwaitingOffering');

    $course->update(['is_active' => true]);
    event(new CourseChanged($course->id));
    event(new CourseChanged($course->id));
    expect($obligations->forStudent($student->id)[0]->state)->toBe('Active');
    $this->assertDatabaseCount('remediation_obligation_transitions', 3);
});

it('freezes a used remediation assignment when the course is later deactivated', function () {
    $student = Student::factory()->create();
    $course = Course::factory()->create(['is_active' => true, 'total_ch' => 3]);
    $semester = Semester::factory()->create([
        'starts_at' => now()->addMonth(),
        'late_add_ends_at' => now()->addMonths(2),
        'grade_submission_deadline_at' => now()->addMonths(5),
        'ends_at' => now()->addMonths(4),
    ]);
    $offering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id]);
    $revocation = CreditDecisionRevocation::query()->create([
        'decision_type' => CreditDecisionType::Exemption,
        'decision_id' => 101,
        'student_id' => $student->id,
        'target_course_id' => $course->id,
        'revoked_by_user_id' => null,
        'reason' => 'corrected evidence',
        'created_at' => now(),
    ]);
    $obligations = app(RemediationObligations::class);
    $initial = $obligations->createFromRevocation($revocation->id);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Preparatory,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => 3,
        'earned' => null,
    ]);

    $course->update(['is_active' => false]);
    event(new CourseChanged($course->id));

    $current = $obligations->forStudent($student->id)[0];
    expect($current->state)->toBe('Active')
        ->and($current->opportunityOfferingId)->toBe($initial->opportunityOfferingId)
        ->and($current->checkpointSemesterId)->toBe($initial->checkpointSemesterId);
});

it('does not freeze a remediation assignment because of an older enrollment in another offering', function () {
    $student = Student::factory()->create();
    $course = Course::factory()->create(['is_active' => true, 'total_ch' => 3]);
    $historical = Semester::factory()->create([
        'registration_opens_at' => now()->subMonths(14),
        'registration_closes_at' => now()->subMonths(13),
        'starts_at' => now()->subYear(),
        'add_drop_ends_at' => now()->subYear()->addWeeks(2),
        'late_add_ends_at' => now()->subYear()->addWeeks(3),
        'withdrawal_deadline_at' => now()->subMonths(10),
        'ends_at' => now()->subMonths(8),
        'grade_submission_deadline_at' => now()->subMonths(7),
    ]);
    $historicalOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $historical->id]);
    $future = Semester::factory()->create([
        'starts_at' => now()->addMonth(),
        'late_add_ends_at' => now()->addMonths(2),
        'grade_submission_deadline_at' => now()->addMonths(5),
        'ends_at' => now()->addMonths(4),
    ]);
    CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $future->id]);
    Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $historicalOffering->id, 'classification' => EnrollmentClassification::Preparatory, 'course_status' => CourseStatus::Regular, 'credit_hours' => 3, 'earned' => null]);
    $revocation = CreditDecisionRevocation::query()->create([
        'decision_type' => CreditDecisionType::Exemption,
        'decision_id' => 102,
        'student_id' => $student->id,
        'target_course_id' => $course->id,
        'reason' => 'corrected evidence',
        'created_at' => now(),
    ]);
    $obligations = app(RemediationObligations::class);
    $obligations->createFromRevocation($revocation->id);

    $course->update(['is_active' => false]);
    event(new CourseChanged($course->id));

    expect($obligations->forStudent($student->id)[0]->state)->toBe('AwaitingOffering');
});
