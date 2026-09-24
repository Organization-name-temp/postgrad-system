<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\MustRetakeObligation;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Support\CapacityOverrideWorkflow;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

it('moves an advisor-approved required full offering to capacity review without minting or reserving a seat', function () {
    $student = Student::factory()->create();
    $course = Course::factory()->create(['total_ch' => 3]);
    $semester = Semester::factory()->create();
    $full = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'capacity' => 0]);
    $originOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => Semester::factory()->create()->id]);
    $origin = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $originOffering->id, 'classification' => EnrollmentClassification::Core, 'course_status' => CourseStatus::Regular, 'credit_hours' => 3, 'earned' => false]);
    MustRetakeObligation::query()->create(['student_id' => $student->id, 'course_id' => $course->id, 'origin_enrollment_id' => $origin->id, 'category' => 'Core', 'credit_hours' => 3, 'state' => 'Active']);
    $registration = RegistrationRequest::factory()->create(['student_id' => $student->id, 'semester_id' => $semester->id, 'status' => RegistrationStatus::Pending]);
    $requestLine = $registration->lines()->create(['course_offering_id' => $full->id, 'mode' => RegistrationMode::Regular]);
    $cart = [new CartLine($full->id, $course->id, $course->code, RegistrationMode::Regular, 3, EnrollmentClassification::Core, 0, [])];

    expect(app(CapacityOverrideWorkflow::class)->startIfRequired($registration->load('lines'), [$requestLine->id => LineDecision::Approved], $cart, User::factory()->create()->id, 'required'))->toBeTrue();
    $this->assertDatabaseHas('registration_requests', ['id' => $registration->id, 'status' => 'AwaitingCapacityOverride']);
    $this->assertDatabaseHas('capacity_override_requirements', ['registration_request_id' => $registration->id, 'course_offering_id' => $full->id, 'state' => 'Pending']);
    expect(Enrollment::query()->where('course_offering_id', $full->id)->count())->toBe(0);
});
