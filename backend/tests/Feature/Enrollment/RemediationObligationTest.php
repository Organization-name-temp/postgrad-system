<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CreditDecisionType;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
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
