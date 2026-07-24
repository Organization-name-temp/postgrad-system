<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The Student's My Schedule read (ticket 06): their minted Enrollments grouped by Semester,
| each with its Course Status, classification and meeting times. Self-contained helpers
| (sch* prefix) so a single-file run works.
*/

/** A signed-in Student, returning the anchor. */
function schStudent(): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    $student = Student::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    return $student;
}

/** An Enrollment for the Student in a fresh Offering in the given Semester. */
function schEnroll(
    Student $student,
    Semester $semester,
    CourseStatus $status = CourseStatus::Regular,
    int $totalCh = 3,
    array $meetings = [[Weekday::Monday, '09:00', '11:00']],
): Enrollment {
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => $totalCh]);
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id, 'course_id' => $course->id]);
    foreach ($meetings as [$weekday, $start, $end]) {
        OfferingMeeting::factory()->slot($weekday, $start, $end)->create(['course_offering_id' => $offering->id]);
    }

    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => $totalCh,
    ]);
}

it('returns the student\'s enrollments grouped by semester, newest first', function () {
    $student = schStudent();
    // Distinct real windows so the newest-first (by start date) order is deterministic:
    // Spring already running (started weeks ago), Fall still ahead.
    $spring = Semester::factory()->running()->create(['term' => Term::Spring, 'year' => 2026]);
    $fall = Semester::factory()->registrationOpen()->create(['term' => Term::Fall, 'year' => 2026]);

    schEnroll($student, $spring);
    schEnroll($student, $fall, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    test()->getJson('/api/v1/registration/schedule')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        // Fall 2026 starts after Spring 2026, so it sorts first.
        ->assertJsonPath('data.0.semester.name_en', 'Fall 2026')
        ->assertJsonPath('data.1.semester.name_en', 'Spring 2026')
        ->assertJsonCount(1, 'data.0.enrollments')
        ->assertJsonPath('data.0.enrollments.0.course_status', 'Regular')
        ->assertJsonPath('data.0.enrollments.0.classification', 'Core');
});

it('renders each enrollment\'s status, classification, credit hours and meetings', function () {
    $student = schStudent();
    $semester = Semester::factory()->create(['term' => Term::Fall, 'year' => 2026]);
    schEnroll($student, $semester, CourseStatus::Audit, totalCh: 3, meetings: [[Weekday::Monday, '09:00', '11:00']]);

    test()->getJson('/api/v1/registration/schedule')
        ->assertOk()
        ->assertJsonPath('data.0.enrollments.0.course_status', 'Audit')
        ->assertJsonPath('data.0.enrollments.0.credit_hours', 3)
        ->assertJsonPath('data.0.enrollments.0.meetings.0.weekday', 1)
        ->assertJsonPath('data.0.enrollments.0.meetings.0.starts_at', '09:00');
});

it('returns an empty schedule when the student has no enrollments', function () {
    schStudent();

    test()->getJson('/api/v1/registration/schedule')
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('403s a schedule read for a user with no student record', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    Sanctum::actingAs($user);

    test()->getJson('/api/v1/registration/schedule')->assertForbidden();
});

it('forbids the schedule to a user without courses.register_self', function () {
    actingAsRole(Role::Instructor);

    test()->getJson('/api/v1/registration/schedule')->assertForbidden();
});

it('requires authentication', function () {
    test()->getJson('/api/v1/registration/schedule')->assertUnauthorized();
});
