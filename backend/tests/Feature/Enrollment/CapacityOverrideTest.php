<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\LineDecision;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\CapacityOverrideRequirement;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\MustRetakeObligation;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Support\CapacityOverrideReconciler;
use Modules\Enrollment\Internal\Support\CapacityOverrideWorkflow;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

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

it('retires a pending capacity requirement and finalizes when a normal seat returns', function () {
    config(['enrollment.min_credit_hours' => 3]);
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $course = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3]);
    CurriculumEntry::factory()->create(['program_id' => $program->id, 'course_id' => $course->id, 'category' => CourseCategory::ProgramCore]);
    $student = Student::factory()->create(['program_id' => $program->id]);
    $semester = Semester::factory()->create();
    $offering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'capacity' => 0]);
    $originOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => Semester::factory()->create()->id]);
    $origin = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $originOffering->id, 'classification' => EnrollmentClassification::Core, 'course_status' => CourseStatus::Regular, 'credit_hours' => 3, 'earned' => false]);
    MustRetakeObligation::query()->create(['student_id' => $student->id, 'course_id' => $course->id, 'origin_enrollment_id' => $origin->id, 'category' => 'Core', 'credit_hours' => 3, 'state' => 'Active']);
    $registration = RegistrationRequest::factory()->create(['student_id' => $student->id, 'semester_id' => $semester->id, 'status' => RegistrationStatus::Pending]);
    $requestLine = $registration->lines()->create(['course_offering_id' => $offering->id, 'mode' => RegistrationMode::Regular]);
    app(CapacityOverrideWorkflow::class)->startIfRequired($registration->load('lines'), [$requestLine->id => LineDecision::Approved], [new CartLine($offering->id, $course->id, $course->code, RegistrationMode::Regular, 3, EnrollmentClassification::Core, 0, [])], $student->advisor_id, 'required');

    $offering->update(['capacity' => 1]);
    app(CapacityOverrideReconciler::class)->reconcileOffering($offering->id);

    $this->assertDatabaseHas('capacity_override_requirements', ['registration_request_id' => $registration->id, 'state' => 'NoLongerRequired']);
    $this->assertDatabaseHas('registration_requests', ['id' => $registration->id, 'status' => RegistrationStatus::Approved->value]);
    $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'course_offering_id' => $offering->id]);
});

it('mints the entire unchanged batch only after scoped capacity approval and final revalidation', function () {
    config(['enrollment.min_credit_hours' => 3]);
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $course = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3]);
    CurriculumEntry::factory()->create(['program_id' => $program->id, 'course_id' => $course->id, 'category' => CourseCategory::ProgramCore]);
    $student = Student::factory()->create(['program_id' => $program->id]);
    $semester = Semester::factory()->create();
    $full = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'capacity' => 0]);
    $originOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => Semester::factory()->create()->id]);
    $origin = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $originOffering->id, 'classification' => EnrollmentClassification::Core, 'course_status' => CourseStatus::Regular, 'credit_hours' => 3, 'earned' => false]);
    MustRetakeObligation::query()->create(['student_id' => $student->id, 'course_id' => $course->id, 'origin_enrollment_id' => $origin->id, 'category' => 'Core', 'credit_hours' => 3, 'state' => 'Active']);
    $registration = RegistrationRequest::factory()->create(['student_id' => $student->id, 'semester_id' => $semester->id, 'status' => RegistrationStatus::Pending]);
    $requestLine = $registration->lines()->create(['course_offering_id' => $full->id, 'mode' => RegistrationMode::Regular]);
    $cart = [new CartLine($full->id, $course->id, $course->code, RegistrationMode::Regular, 3, EnrollmentClassification::Core, 0, [])];
    app(CapacityOverrideWorkflow::class)->startIfRequired($registration->load('lines'), [$requestLine->id => LineDecision::Approved], $cart, $student->advisor_id, 'required');
    $requirement = CapacityOverrideRequirement::query()->firstOrFail();
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create(['user_id' => $head->id, 'department_id' => $department->id]);
    Sanctum::actingAs($head);

    $this->postJson("/api/v1/registration/capacity-overrides/{$requirement->id}/decision", ['action' => 'approve', 'reason' => 'Mandatory retake.'])
        ->assertOk();

    $this->assertDatabaseHas('registration_requests', ['id' => $registration->id, 'status' => RegistrationStatus::Approved->value]);
    $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'course_offering_id' => $full->id, 'course_status' => CourseStatus::Retake->value]);
});

it('rolls back capacity approval when final whole-batch revalidation finds a duplicate Enrollment', function () {
    config(['enrollment.min_credit_hours' => 3]);
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $course = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3]);
    CurriculumEntry::factory()->create(['program_id' => $program->id, 'course_id' => $course->id, 'category' => CourseCategory::ProgramCore]);
    $student = Student::factory()->create(['program_id' => $program->id]);
    $semester = Semester::factory()->create();
    $full = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => $semester->id, 'capacity' => 0]);
    $originOffering = CourseOffering::factory()->create(['course_id' => $course->id, 'semester_id' => Semester::factory()->create()->id]);
    $origin = Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $originOffering->id, 'classification' => EnrollmentClassification::Core, 'course_status' => CourseStatus::Regular, 'credit_hours' => 3, 'earned' => false]);
    MustRetakeObligation::query()->create(['student_id' => $student->id, 'course_id' => $course->id, 'origin_enrollment_id' => $origin->id, 'category' => 'Core', 'credit_hours' => 3, 'state' => 'Active']);
    $registration = RegistrationRequest::factory()->create(['student_id' => $student->id, 'semester_id' => $semester->id, 'status' => RegistrationStatus::Pending]);
    $requestLine = $registration->lines()->create(['course_offering_id' => $full->id, 'mode' => RegistrationMode::Regular]);
    app(CapacityOverrideWorkflow::class)->startIfRequired($registration->load('lines'), [$requestLine->id => LineDecision::Approved], [new CartLine($full->id, $course->id, $course->code, RegistrationMode::Regular, 3, EnrollmentClassification::Core, 0, [])], $student->advisor_id, 'required');
    Enrollment::factory()->create(['student_id' => $student->id, 'course_offering_id' => $full->id, 'classification' => EnrollmentClassification::Core, 'course_status' => CourseStatus::Retake, 'credit_hours' => 3]);
    $requirement = CapacityOverrideRequirement::query()->firstOrFail();
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create(['user_id' => $head->id, 'department_id' => $department->id]);
    Sanctum::actingAs($head);

    $this->postJson("/api/v1/registration/capacity-overrides/{$requirement->id}/decision", ['action' => 'approve', 'reason' => 'Mandatory retake.'])
        ->assertUnprocessable();

    $this->assertDatabaseHas('capacity_override_requirements', ['id' => $requirement->id, 'state' => 'Pending']);
    $this->assertDatabaseHas('registration_requests', ['id' => $registration->id, 'status' => RegistrationStatus::AwaitingCapacityOverride->value]);
    expect(Enrollment::query()->where('student_id', $student->id)->where('course_offering_id', $full->id)->count())->toBe(1);
});
