<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Listeners\ReconcileMustRetakeObligations;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\CreditDecisionRevocation;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\MustRetakeObligation;
use Modules\Enrollment\Internal\Models\RemediationObligation;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Enrollment\Internal\Support\CartViolation;
use Modules\Enrollment\Internal\Support\RequiredRetakeValidator;
use Modules\Enrollment\Public\Events\GradeOutcomesFinalized;
use Modules\Identity\Internal\Models\Student;

uses(RefreshDatabase::class);

it('creates one historical obligation for a failed preparatory or core outcome and closes it after success', function () {
    $student = Student::factory()->create();
    $course = Course::factory()->create(['total_ch' => 3]);
    $semester = Semester::factory()->create();
    $offering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id]);
    $failed = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => 3,
        'earned' => false,
    ]);

    $listener = app(ReconcileMustRetakeObligations::class);
    $listener->handle(new GradeOutcomesFinalized(1, [$failed->id]));
    $listener->handle(new GradeOutcomesFinalized(1, [$failed->id]));
    $this->assertDatabaseCount('must_retake_obligations', 1);
    $this->assertDatabaseHas('must_retake_obligations', ['student_id' => $student->id, 'course_id' => $course->id, 'state' => 'Active']);

    $retakeOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => Semester::factory()->create()->id]);
    $passed = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $retakeOffering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Retake,
        'credit_hours' => 3,
        'earned' => true,
    ]);
    $listener->handle(new GradeOutcomesFinalized(2, [$passed->id]));
    $this->assertDatabaseHas('must_retake_obligations', [
        'student_id' => $student->id,
        'course_id' => $course->id,
        'state' => 'Satisfied',
        'closed_by_enrollment_id' => $passed->id,
    ]);
});

it('gives an active Remediation Obligation the same offered-course priority', function () {
    $student = Student::factory()->create();
    $semester = Semester::factory()->create();
    $requiredCourse = Course::factory()->create(['total_ch' => 1]);
    CourseOffering::factory()->create(['course_id' => $requiredCourse->id, 'semester_id' => $semester->id]);
    $revocation = CreditDecisionRevocation::query()->create(['decision_type' => 'exemption', 'decision_id' => 987, 'student_id' => $student->id, 'target_course_id' => $requiredCourse->id, 'reason' => 'corrected', 'created_at' => now()]);
    RemediationObligation::query()->create(['student_id' => $student->id, 'course_id' => $requiredCourse->id, 'revocation_id' => $revocation->id, 'state' => 'Active', 'checkpoint_semester_id' => $semester->id, 'effective_deadline_at' => now()->addMonth()]);
    $otherCourse = Course::factory()->create(['total_ch' => 3]);
    $otherOffering = CourseOffering::factory()->create(['course_id' => $otherCourse->id, 'semester_id' => $semester->id]);
    $line = new CartLine($otherOffering->id, $otherCourse->id, $otherCourse->code, RegistrationMode::Regular, 3, EnrollmentClassification::Core, 10, []);

    expect(app(RequiredRetakeValidator::class)->validate($student->id, $semester->id, [$line], 12)[0]->code)
        ->toBe(CartViolation::MUST_RETAKE_REQUIRED);
});

it('requires an offered feasible obligation before unrelated for-credit study', function () {
    $student = Student::factory()->create();
    $semester = Semester::factory()->create();
    $requiredCourse = Course::factory()->create(['total_ch' => 3]);
    $requiredOffering = CourseOffering::factory()->create(['course_id' => $requiredCourse->id, 'semester_id' => $semester->id]);
    $originOffering = CourseOffering::factory()->create(['course_id' => $requiredCourse->id, 'semester_id' => Semester::factory()->create()->id]);
    $origin = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $originOffering->id,
        'classification' => EnrollmentClassification::Preparatory,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => 3,
        'earned' => false,
    ]);
    MustRetakeObligation::query()->create([
        'student_id' => $student->id,
        'course_id' => $requiredCourse->id,
        'origin_enrollment_id' => $origin->id,
        'category' => 'Preparatory',
        'credit_hours' => 3,
        'state' => 'Active',
    ]);
    $otherCourse = Course::factory()->create(['total_ch' => 3]);
    $otherOffering = CourseOffering::factory()->create(['course_id' => $otherCourse->id, 'semester_id' => $semester->id]);
    $line = fn (CourseOffering $offering, Course $course): CartLine => new CartLine(
        $offering->id, $course->id, $course->code, RegistrationMode::Regular, 3,
        EnrollmentClassification::Core, 10, [],
    );
    $validator = new RequiredRetakeValidator;

    expect($validator->validate($student->id, $semester->id, [$line($otherOffering, $otherCourse)], 12)[0]->code)
        ->toBe(CartViolation::MUST_RETAKE_REQUIRED)
        ->and($validator->validate($student->id, $semester->id, [$line($requiredOffering, $requiredCourse)], 12))->toBe([]);
});
