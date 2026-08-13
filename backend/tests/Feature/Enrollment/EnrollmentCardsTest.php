<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The two Enrollment Dashboard Cards (phase 4.5, ticket 04) — the registration loop from both ends:
| My Registration (the Student's load and open window) and Advisee Requests (the Advisor's queue).
|
| Period state and the credit-hour bounds are derivations already covered at their own seams (the
| SemesterCalendar unit test, RegistrationSubmissionTest), so what is asserted here is that the
| Cards *read* them, and the scoping that is the correctness risk: an Advisor's Card counts only
| their **own live** advisees. Self-contained helpers (ec* prefix) so a single-file run works.
*/

function ecAdvisor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Advisor);

    return $user;
}

/**
 * A Student anchored to a fresh R02 user. Every Student has an Advisor (the column is not
 * nullable), so omitting `$advisor` leaves the factory to mint one rather than clearing it.
 */
function ecStudent(?User $advisor = null): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);

    return Student::factory()->create(array_filter([
        'user_id' => $user->id,
        'advisor_id' => $advisor?->id,
    ]));
}

/** An active Enrollment for the Student in `$semester`, carrying `$creditHours`. */
function ecEnroll(Student $student, Semester $semester, int $creditHours = 3, CourseStatus $status = CourseStatus::Regular): Enrollment
{
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => Course::factory()->create(['total_ch' => $creditHours])->id,
        'instructor_id' => User::factory()->create()->id,
    ]);

    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => $creditHours,
    ]);
}

/** A Pending Initial request for the Student with `$lines` lines on it. */
function ecPending(Student $student, Semester $semester, int $lines = 1): RegistrationRequest
{
    $request = RegistrationRequest::factory()->pending()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]);

    for ($i = 0; $i < $lines; $i++) {
        $offering = CourseOffering::factory()->create([
            'semester_id' => $semester->id,
            'course_id' => Course::factory()->create(['total_ch' => 3])->id,
            'instructor_id' => User::factory()->create()->id,
        ]);
        $request->lines()->create([
            'course_offering_id' => $offering->id,
            'mode' => RegistrationMode::Regular,
        ]);
    }

    return $request;
}

function ecRegistrationCard()
{
    return test()->getJson('/api/v1/enrollment/cards/my-registration');
}

function ecAdviseeCard()
{
    return test()->getJson('/api/v1/enrollment/cards/advisee-requests');
}

// ---------------------------------------------------------------------------
// My Registration — the load, the bounds, and the open window
// ---------------------------------------------------------------------------

it('totals the current semester’s credit hours and states the 6–12 bounds alongside them', function () {
    $semester = Semester::factory()->running()->create();
    $student = ecStudent();
    ecEnroll($student, $semester, 3);
    ecEnroll($student, $semester, 3);
    // A different term's Enrollment is not this semester's load.
    ecEnroll($student, Semester::factory()->ended()->create(), 3);

    Sanctum::actingAs($student->user);

    ecRegistrationCard()->assertOk()
        ->assertJsonPath('data.semester.id', $semester->id)
        ->assertJsonPath('data.semester.name_en', $semester->name_en)
        ->assertJsonPath('data.credit_hours', 6)
        ->assertJsonPath('data.enrollment_count', 2)
        // The bounds travel with the number so it is anchored rather than left to be judged —
        // read from the existing validator's configuration, not restated here.
        ->assertJsonPath('data.min_credit_hours', (int) config('enrollment.min_credit_hours'))
        ->assertJsonPath('data.max_credit_hours', (int) config('enrollment.max_credit_hours'));
});

it('excludes a withdrawn enrollment from the load, as the schedule does', function () {
    $semester = Semester::factory()->running()->create();
    $student = ecStudent();
    ecEnroll($student, $semester, 3);
    ecEnroll($student, $semester, 3, CourseStatus::Withdrawn);

    Sanctum::actingAs($student->user);

    ecRegistrationCard()->assertOk()
        ->assertJsonPath('data.credit_hours', 3)
        ->assertJsonPath('data.enrollment_count', 1);
});

it('names the open period and the date it closes, read from the derived window', function () {
    // The `running` factory state sits past late-add and before the withdrawal deadline.
    $semester = Semester::factory()->running()->create();
    $student = ecStudent();

    Sanctum::actingAs($student->user);

    ecRegistrationCard()->assertOk()
        ->assertJsonPath('data.current_period', 'withdrawal')
        ->assertJsonPath('data.period_ends_at', $semester->withdrawal_deadline_at->toIso8601String());
});

it('reports the registration window when the term has not started yet', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $student = ecStudent();

    Sanctum::actingAs($student->user);

    ecRegistrationCard()->assertOk()
        ->assertJsonPath('data.semester.id', $semester->id)
        ->assertJsonPath('data.current_period', 'registration')
        ->assertJsonPath('data.period_ends_at', $semester->registration_closes_at->toIso8601String());
});

it('returns a null closing date when no window is open, because `closed` is not a window', function () {
    // A term still running with every change window behind it: there is a current period, but it
    // is not one that closes, so a closing date would be a fiction.
    $semester = Semester::factory()->create([
        'registration_opens_at' => now()->subWeeks(20),
        'registration_closes_at' => now()->subWeeks(18),
        'starts_at' => now()->subWeeks(16),
        'add_drop_ends_at' => now()->subWeeks(14),
        'late_add_ends_at' => now()->subWeeks(13),
        'withdrawal_deadline_at' => now()->subWeek(),
        'ends_at' => now()->addWeeks(4),
        'grade_submission_deadline_at' => now()->addWeeks(6),
    ]);
    $student = ecStudent();

    Sanctum::actingAs($student->user);

    ecRegistrationCard()->assertOk()
        ->assertJsonPath('data.semester.id', $semester->id)
        ->assertJsonPath('data.current_period', 'closed')
        ->assertJsonPath('data.period_ends_at', null);
});

it('returns a null semester with the bounds intact when no term is live', function () {
    Semester::factory()->ended()->create();
    $student = ecStudent();

    Sanctum::actingAs($student->user);

    ecRegistrationCard()->assertOk()
        ->assertJsonPath('data.semester', null)
        ->assertJsonPath('data.current_period', null)
        ->assertJsonPath('data.credit_hours', 0)
        ->assertJsonPath('data.min_credit_hours', (int) config('enrollment.min_credit_hours'));
});

it('returns a zero load for a student with no enrollments this semester', function () {
    Semester::factory()->running()->create();
    $student = ecStudent();

    Sanctum::actingAs($student->user);

    ecRegistrationCard()->assertOk()
        ->assertJsonPath('data.credit_hours', 0)
        ->assertJsonPath('data.enrollment_count', 0);
});

it('refuses a user who does not hold courses.register_self', function () {
    actingAsRole(Role::Instructor);

    ecRegistrationCard()->assertForbidden();
});

it('refuses an administrator who inherits the permission but holds no student anchor', function () {
    // R09 inherits every permission (ADR-0008) and has no Student record — the exact case that
    // makes this Card gate on the **Role** in the SPA rather than the Permission.
    actingAsRole(Role::SystemAdmin);

    ecRegistrationCard()->assertForbidden();
});

it('refuses an unauthenticated request for the registration card', function () {
    ecRegistrationCard()->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Advisee Requests — the count, the advisee load, and the scoping that matters
// ---------------------------------------------------------------------------

it('counts the pending requests waiting on the advisor, with the oldest few named', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $advisor = ecAdvisor();
    $student = ecStudent($advisor);
    $request = ecPending($student, $semester, lines: 2);

    Sanctum::actingAs($advisor);

    ecAdviseeCard()->assertOk()
        ->assertJsonPath('data.pending_count', 1)
        ->assertJsonPath('data.advisee_count', 1)
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.requests.0.id', (int) $request->getKey())
        ->assertJsonPath('data.requests.0.student_id', $student->id)
        // The Student is named, so the count is actionable rather than abstract.
        ->assertJsonPath('data.requests.0.student_name', $student->user->name)
        ->assertJsonPath('data.requests.0.university_id', $student->university_id)
        ->assertJsonPath('data.requests.0.course_count', 2);
});

it('counts only the acting advisor’s own advisees, never another advisor’s', function () {
    // The correctness risk of the whole ticket.
    $semester = Semester::factory()->registrationOpen()->create();
    $mine = ecAdvisor();
    $theirs = ecAdvisor();
    ecPending(ecStudent($mine), $semester);
    ecPending(ecStudent($theirs), $semester);

    Sanctum::actingAs($mine);

    ecAdviseeCard()->assertOk()
        ->assertJsonPath('data.pending_count', 1)
        ->assertJsonPath('data.advisee_count', 1);
});

it('follows advisor reassignment, because the advisee set is resolved live', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $before = ecAdvisor();
    $after = ecAdvisor();
    $student = ecStudent($before);
    ecPending($student, $semester);

    $student->update(['advisor_id' => $after->id]);

    Sanctum::actingAs($before);
    ecAdviseeCard()->assertOk()->assertJsonPath('data.pending_count', 0);

    Sanctum::actingAs($after);
    ecAdviseeCard()->assertOk()->assertJsonPath('data.pending_count', 1);
});

it('shows a reviewer with an empty queue a zero count rather than erroring', function () {
    $advisor = ecAdvisor();
    ecStudent($advisor);

    Sanctum::actingAs($advisor);

    ecAdviseeCard()->assertOk()
        ->assertJsonPath('data.pending_count', 0)
        ->assertJsonPath('data.advisee_count', 1)
        ->assertJsonPath('data.requests', []);
});

it('counts every advisor’s advisees for student affairs, who review unscoped', function () {
    // Scoping an oversight reviewer to "their advisees" would show nothing: they hold none, which
    // is why `advisee_count` is null for them rather than zero.
    $semester = Semester::factory()->registrationOpen()->create();
    ecPending(ecStudent(ecAdvisor()), $semester);
    ecPending(ecStudent(ecAdvisor()), $semester);

    actingAsRole(Role::StudentAffairs);

    ecAdviseeCard()->assertOk()
        ->assertJsonPath('data.pending_count', 2)
        ->assertJsonPath('data.advisee_count', null);
});

it('counts only pending requests, not ones already decided', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $advisor = ecAdvisor();
    $student = ecStudent($advisor);
    ecPending($student, $semester);
    RegistrationRequest::factory()->rejected()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]);

    Sanctum::actingAs($advisor);

    ecAdviseeCard()->assertOk()->assertJsonPath('data.pending_count', 1);
});

it('refuses a user who does not hold registration.approve', function () {
    actingAsRole(Role::Instructor);

    ecAdviseeCard()->assertForbidden();
});

it('refuses an unauthenticated request for the advisee card', function () {
    ecAdviseeCard()->assertUnauthorized();
});
