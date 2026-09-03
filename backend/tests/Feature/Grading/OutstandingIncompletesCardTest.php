<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The Outstanding Incompletes Dashboard Card (phase 4.5, ticket 08) — how many Incompletes the
| acting Instructor has left waiting on their mark, and when the nearest one is due.
|
| This Card earns its place by preventing real harm: an unresolved Incomplete converts to a Fail
| Withdrawal when its deadline passes, and that conversion is the single state-changing scheduled
| command in the system. The deadline arithmetic itself is covered at its own seam by
| IncompleteDeadlineTest/IncompleteCommandsTest, so what is asserted here is that the Card *reads*
| that derivation rather than storing anything — a corrected Semester start moves the Card with it —
| that an Incomplete with no following Semester is shown without a fabricated deadline, and who is
| allowed to read it. Self-contained helpers (oic* prefix) so a single-file run works.
*/

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
});

function oicCard()
{
    return test()->getJson('/api/v1/grading/cards/incompletes');
}

function oicInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/** A Semester starting on the given date — the only thing the derived deadline turns on. */
function oicSemester(string $startsAt): Semester
{
    return Semester::factory()->create(['starts_at' => Carbon::parse($startsAt)]);
}

/**
 * One outstanding Incomplete on an Offering `$instructor` teaches, declared in `$semester`. Its
 * deadline is derived from whichever Semester starts next, so a case that wants none simply
 * declares in the latest Semester on the calendar.
 */
function oicIncomplete(User $instructor, Semester $semester): Incomplete
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    $student = Student::factory()->create(['user_id' => $user->id]);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'instructor_id' => $instructor->id,
    ]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Incomplete,
        'pre_incomplete_status' => CourseStatus::Regular,
        'credit_hours' => 3,
    ]);

    return Incomplete::factory()->create([
        'declared_on_grade_sheet_id' => GradeSheet::factory()->create(['course_offering_id' => $offering->id])->id,
        'enrollment_id' => $enrollment->id,
        'declared_by' => $instructor->id,
    ]);
}

// ---------------------------------------------------------------------------
// The count and the nearest deadline
// ---------------------------------------------------------------------------

it('counts the outstanding Incompletes and gives the nearest derived deadline', function () {
    $instructor = oicInstructor();

    // Three Semesters, so each Incomplete's deadline is the *following* Semester's start + 63 days
    // (SRS §4.7.3.2). The earlier term's Incomplete therefore comes due first.
    $spring = oicSemester('2026-02-01');
    $fall = oicSemester('2026-09-01');
    oicSemester('2027-02-01');

    oicIncomplete($instructor, $spring); // due 2026-09-01 + 63 = 2026-11-03
    oicIncomplete($instructor, $fall);   // due 2027-02-01 + 63 = 2027-04-05

    Sanctum::actingAs($instructor);

    oicCard()->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta', [])
        ->assertJsonPath('data.outstanding_count', 2)
        ->assertJsonPath('data.without_deadline_count', 0)
        ->assertJsonPath('data.nearest_deadline', Carbon::parse('2026-11-03')->toIso8601String());
});

it('reads the deadline fresh, so correcting a Semester’s start date moves it', function () {
    $instructor = oicInstructor();
    $spring = oicSemester('2026-02-01');
    $next = oicSemester('2026-09-01');
    oicIncomplete($instructor, $spring);

    Sanctum::actingAs($instructor);
    oicCard()->assertJsonPath('data.nearest_deadline', Carbon::parse('2026-11-03')->toIso8601String());

    // Nothing is stored and nothing is cached (ADR-0018), so an admin fixing the calendar moves
    // every outstanding deadline — and this Card — with it.
    $next->update(['starts_at' => Carbon::parse('2026-10-01')]);

    oicCard()->assertJsonPath('data.nearest_deadline', Carbon::parse('2026-12-03')->toIso8601String());
});

it('shows an Incomplete whose following Semester does not exist yet, without inventing a deadline', function () {
    $instructor = oicInstructor();
    // The latest Semester on the calendar: nothing follows it, so no deadline can be computed.
    oicIncomplete($instructor, oicSemester('2026-09-01'));

    Sanctum::actingAs($instructor);

    // The conversion command handles this case explicitly rather than crashing on it, and so does
    // the Card: the work is still the Instructor's, and an admin's omission must not hide it.
    oicCard()->assertOk()
        ->assertJsonPath('data.outstanding_count', 1)
        ->assertJsonPath('data.without_deadline_count', 1)
        ->assertJsonPath('data.nearest_deadline', null);
});

it('counts an Incomplete with no computable deadline beside ones that have theirs', function () {
    $instructor = oicInstructor();
    $spring = oicSemester('2026-02-01');
    $latest = oicSemester('2026-09-01');

    oicIncomplete($instructor, $spring);
    oicIncomplete($instructor, $latest);

    Sanctum::actingAs($instructor);

    oicCard()->assertOk()
        ->assertJsonPath('data.outstanding_count', 2)
        ->assertJsonPath('data.without_deadline_count', 1)
        ->assertJsonPath('data.nearest_deadline', Carbon::parse('2026-11-03')->toIso8601String());
});

// ---------------------------------------------------------------------------
// Scope — the Card counts what the resolution screen lists, and nothing else
// ---------------------------------------------------------------------------

it('counts only the acting Instructor’s own Incompletes', function () {
    $mine = oicInstructor();
    $theirs = oicInstructor();
    $spring = oicSemester('2026-02-01');
    oicSemester('2026-09-01');

    oicIncomplete($mine, $spring);
    oicIncomplete($theirs, $spring);
    oicIncomplete($theirs, $spring);

    Sanctum::actingAs($mine);

    oicCard()->assertOk()->assertJsonPath('data.outstanding_count', 1);
});

it('counts exactly what the resolution screen lists', function () {
    $instructor = oicInstructor();
    $spring = oicSemester('2026-02-01');
    oicSemester('2026-09-01');
    oicIncomplete($instructor, $spring);
    oicIncomplete($instructor, $spring);

    Sanctum::actingAs($instructor);

    // Both read the one seam, so the Card can never send an Instructor to a screen showing a
    // different number from the one that sent them there.
    $listed = count(test()->getJson('/api/v1/grading/incompletes')->assertOk()->json('data'));

    oicCard()->assertOk()->assertJsonPath('data.outstanding_count', $listed);
});

it('is empty rather than erroring for an Instructor with nothing outstanding', function () {
    Sanctum::actingAs(oicInstructor());

    oicCard()->assertOk()
        ->assertJsonPath('data.outstanding_count', 0)
        ->assertJsonPath('data.without_deadline_count', 0)
        ->assertJsonPath('data.nearest_deadline', null);
});

it('refuses a central-entry user who shares the permission but is not an instructor', function () {
    $instructor = oicInstructor();
    $spring = oicSemester('2026-02-01');
    oicSemester('2026-09-01');
    oicIncomplete($instructor, $spring);

    // Student Affairs holds `grades.enter` (§3.2), but this work belongs only to R04.
    actingAsRole(Role::StudentAffairs);

    oicCard()->assertForbidden();
});

// ---------------------------------------------------------------------------
// Authorization — the server decides, not the client's choice of what to request
// ---------------------------------------------------------------------------

it('refuses a user without grades.enter', function () {
    // A Department Head holds `grades.finalize`, never `grades.enter` (§3.2).
    actingAsRole(Role::DepartmentHead);

    oicCard()->assertForbidden();
});

it('refuses an unauthenticated request', function () {
    oicCard()->assertUnauthorized();
});
