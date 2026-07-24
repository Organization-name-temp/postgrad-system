<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\AttendanceMark;
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The attendance register (ticket 08, ADR-0019, a deliberate SRS extension). Written against
| the /api/v1 HTTP seam (real Postgres, full HTTP): the assigned Instructor generates sessions
| by opening the register, cancels/reinstates them, marks a binary present/absent register,
| and sees derived percentages; authorisation is by assignment (no permission), and the only
| consequence is the audit-at-risk notification, fired once on the downward crossing.
| Self-contained helpers (att* prefix) so a single-file run works.
*/

/** An instructor user (R04). */
function attInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/**
 * A Spring 2026 Semester whose start→end window (Feb 16 → Mar 2, both Mondays) contains
 * exactly three Mondays — so a Monday-only offering generates exactly three sessions.
 */
function attSemester(): Semester
{
    return Semester::factory()->create([
        'term' => Term::Spring,
        'year' => 2026,
        'registration_opens_at' => Carbon::parse('2026-01-01'),
        'registration_closes_at' => Carbon::parse('2026-02-10'),
        'starts_at' => Carbon::parse('2026-02-16'),
        'add_drop_ends_at' => Carbon::parse('2026-02-28'),
        'late_add_ends_at' => Carbon::parse('2026-03-01'),
        'withdrawal_deadline_at' => Carbon::parse('2026-03-02'),
        'ends_at' => Carbon::parse('2026-03-02'),
    ]);
}

/** An Offering taught by the instructor, with a single weekly Monday meeting. */
function attOffering(User $instructor, Semester $semester, Weekday $day = Weekday::Monday): CourseOffering
{
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
        'capacity' => 30,
    ]);
    OfferingMeeting::factory()->slot($day, '09:00', '11:00')->create(['course_offering_id' => $offering->id]);

    return $offering;
}

/** A fresh Student (R02) enrolled in the Offering, in the given course status. */
function attEnroll(CourseOffering $offering, CourseStatus $status = CourseStatus::Regular): Enrollment
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    $student = Student::factory()->create(['user_id' => $user->id]);

    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => 3,
    ]);
}

/** A class session on a specific date for the Offering. */
function attSession(CourseOffering $offering, string $date): ClassSession
{
    return ClassSession::factory()->on($date)->create(['course_offering_id' => $offering->id]);
}

/** PUT one session's register as the acting user. */
function attMark(ClassSession $session, Enrollment $enrollment, bool $present)
{
    return test()->putJson("/api/v1/class-sessions/{$session->id}/attendance", [
        'marks' => [['enrollment_id' => $enrollment->id, 'present' => $present]],
    ]);
}

// ---------------------------------------------------------------------------
// Session generation
// ---------------------------------------------------------------------------

it('generates class sessions from the meeting rows when the register is opened', function () {
    $instructor = attInstructor();
    $semester = attSemester();
    $offering = attOffering($instructor, $semester);
    Sanctum::actingAs($instructor);

    $response = test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();

    // Feb 16, 23, Mar 2 — the three Mondays in the window.
    expect($response->json('data.sessions'))->toHaveCount(3);
    expect($response->json('data.sessions.0.date'))->toBe('2026-02-16');
    expect($response->json('data.sessions.0.weekday'))->toBe(Weekday::Monday->value);

    $this->assertDatabaseCount('class_sessions', 3);
});

it('generates sessions idempotently — reopening the register never duplicates them', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    Sanctum::actingAs($instructor);

    test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();
    test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();

    $this->assertDatabaseCount('class_sessions', 3);
});

// ---------------------------------------------------------------------------
// My Offerings
// ---------------------------------------------------------------------------

it('lists only the acting instructor\'s own offerings in My Offerings', function () {
    $semester = attSemester();
    $mine = attOffering($instructor = attInstructor(), $semester);
    $theirs = attOffering(attInstructor(), $semester);
    attEnroll($mine);

    Sanctum::actingAs($instructor);
    $response = test()->getJson('/api/v1/offerings/mine')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');
    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($theirs->id);
    expect($response->json('data.0.student_count'))->toBe(1);
});

// ---------------------------------------------------------------------------
// Authorization by assignment
// ---------------------------------------------------------------------------

it('forbids a non-assigned instructor from reading, cancelling, and marking', function () {
    $owner = attInstructor();
    $offering = attOffering($owner, attSemester());
    $session = attSession($offering, '2026-02-16');
    $enrollment = attEnroll($offering);

    // A different instructor — not this offering's assignee.
    Sanctum::actingAs(attInstructor());

    test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertForbidden();
    test()->patchJson("/api/v1/class-sessions/{$session->id}", ['cancelled' => true])->assertForbidden();
    attMark($session, $enrollment, true)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Cancel / reinstate
// ---------------------------------------------------------------------------

it('cancels and reinstates a session', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $session = attSession($offering, '2026-02-16');
    Sanctum::actingAs($instructor);

    test()->patchJson("/api/v1/class-sessions/{$session->id}", ['cancelled' => true])
        ->assertOk()
        ->assertJsonPath('data.cancelled', true);
    expect($session->fresh()->cancelled_at)->not->toBeNull();

    test()->patchJson("/api/v1/class-sessions/{$session->id}", ['cancelled' => false])
        ->assertOk()
        ->assertJsonPath('data.cancelled', false);
    expect($session->fresh()->cancelled_at)->toBeNull();
});

// ---------------------------------------------------------------------------
// Marking + correction + derived percentage
// ---------------------------------------------------------------------------

it('marks present/absent and corrects a mark by overwriting it', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $session = attSession($offering, '2026-02-16');
    $enrollment = attEnroll($offering);
    Sanctum::actingAs($instructor);

    attMark($session, $enrollment, false)->assertOk();
    $this->assertDatabaseHas('attendance_marks', [
        'class_session_id' => $session->id,
        'enrollment_id' => $enrollment->id,
        'present' => false,
    ]);

    // Correct it to present — the same (session, enrollment) row is overwritten, not doubled.
    attMark($session, $enrollment, true)->assertOk();
    expect(AttendanceMark::query()->where('enrollment_id', $enrollment->id)->count())->toBe(1);
    $this->assertDatabaseHas('attendance_marks', [
        'class_session_id' => $session->id,
        'enrollment_id' => $enrollment->id,
        'present' => true,
    ]);
});

it('derives the percentage over non-cancelled recorded sessions', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $enrollment = attEnroll($offering);
    $s1 = attSession($offering, '2026-02-16');
    $s2 = attSession($offering, '2026-02-23');
    $s3 = attSession($offering, '2026-03-02');
    Sanctum::actingAs($instructor);

    attMark($s1, $enrollment, true)->assertOk();
    attMark($s2, $enrollment, true)->assertOk();
    attMark($s3, $enrollment, false)->assertOk(); // 2 / 3 = 67%

    $before = test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();
    expect($before->json('data.roster.0.attendance.percentage'))->toBe(67)
        ->and($before->json('data.roster.0.attendance.recorded'))->toBe(3);

    // Cancelling the absent session drops it from the denominator → 2 / 2 = 100%.
    test()->patchJson("/api/v1/class-sessions/{$s3->id}", ['cancelled' => true])->assertOk();

    $after = test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();
    expect($after->json('data.roster.0.attendance.percentage'))->toBe(100)
        ->and($after->json('data.roster.0.attendance.recorded'))->toBe(2);
});

it('409s marking a cancelled session', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $session = attSession($offering, '2026-02-16');
    $session->update(['cancelled_at' => now()]);
    $enrollment = attEnroll($offering);
    Sanctum::actingAs($instructor);

    attMark($session, $enrollment, true)->assertStatus(409);
});

it('422s marking an enrollment that is not on the active roster', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $session = attSession($offering, '2026-02-16');
    // A withdrawn student has left the active roster.
    $withdrawn = attEnroll($offering, CourseStatus::Withdrawn);
    Sanctum::actingAs($instructor);

    attMark($session, $withdrawn, true)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['marks']);
});

it('excludes withdrawn enrollments from the roster but keeps their marks', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $active = attEnroll($offering);
    $withdrawn = attEnroll($offering, CourseStatus::Withdrawn);
    Sanctum::actingAs($instructor);

    $response = test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();
    $rosterIds = collect($response->json('data.roster'))->pluck('enrollment_id');

    expect($rosterIds)->toContain($active->id)
        ->and($rosterIds)->not->toContain($withdrawn->id);
});

// ---------------------------------------------------------------------------
// Audit-at-risk flag + notification
// ---------------------------------------------------------------------------

it('raises the audit-at-risk notification once on the downward crossing', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $enrollment = attEnroll($offering, CourseStatus::Audit);
    $student = Student::query()->find($enrollment->student_id);

    $s1 = attSession($offering, '2026-02-16');
    $s2 = attSession($offering, '2026-02-23');
    $s3 = attSession($offering, '2026-03-02');
    $s4 = attSession($offering, '2026-03-09');
    Sanctum::actingAs($instructor);

    attMark($s1, $enrollment, true)->assertOk();  // 100%
    attMark($s2, $enrollment, true)->assertOk();  // 100%
    attMark($s3, $enrollment, false)->assertOk(); // 2/3 = 67% → crosses below → notify
    attMark($s4, $enrollment, false)->assertOk(); // 2/4 = 50% → already below → no new notify

    expect(
        DB::table('notifications')
            ->where('user_id', $student->user_id)
            ->where('type', 'attendance.audit_at_risk')
            ->count()
    )->toBe(1);

    // The chip is visible on the register too.
    $response = test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();
    expect($response->json('data.roster.0.attendance.at_risk'))->toBeTrue();
});

it('raises audit-at-risk when cancelling a session drops the percentage below the threshold', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $enrollment = attEnroll($offering, CourseStatus::Audit);
    $student = Student::query()->find($enrollment->student_id);

    $s1 = attSession($offering, '2026-02-16');
    $s2 = attSession($offering, '2026-02-23');
    $s3 = attSession($offering, '2026-03-02');
    $s4 = attSession($offering, '2026-03-09');
    Sanctum::actingAs($instructor);

    // Present in 3 of 4 → 75% exactly (not below, so marking raises no flag).
    attMark($s1, $enrollment, true)->assertOk();
    attMark($s2, $enrollment, true)->assertOk();
    attMark($s3, $enrollment, true)->assertOk();
    attMark($s4, $enrollment, false)->assertOk();
    expect(DB::table('notifications')->where('user_id', $student->user_id)->count())->toBe(0);

    // Cancelling a present session drops the denominator → 2/3 = 67% → a downward crossing.
    test()->patchJson("/api/v1/class-sessions/{$s1->id}", ['cancelled' => true])->assertOk();

    expect(
        DB::table('notifications')
            ->where('user_id', $student->user_id)
            ->where('type', 'attendance.audit_at_risk')
            ->count()
    )->toBe(1);
});

it('does not raise audit-at-risk for a Regular enrollment below the threshold', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $enrollment = attEnroll($offering, CourseStatus::Regular);
    $student = Student::query()->find($enrollment->student_id);

    $s1 = attSession($offering, '2026-02-16');
    $s2 = attSession($offering, '2026-02-23');
    Sanctum::actingAs($instructor);

    attMark($s1, $enrollment, false)->assertOk();
    attMark($s2, $enrollment, false)->assertOk(); // 0% — but Regular, never at risk

    expect(
        DB::table('notifications')->where('user_id', $student->user_id)->count()
    )->toBe(0);

    $response = test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();
    expect($response->json('data.roster.0.attendance.at_risk'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Student My Schedule — the derived percentage
// ---------------------------------------------------------------------------

it('shows the student their attendance percentage on My Schedule', function () {
    $instructor = attInstructor();
    $offering = attOffering($instructor, attSemester());
    $enrollment = attEnroll($offering, CourseStatus::Audit);
    $student = Student::query()->find($enrollment->student_id);

    $s1 = attSession($offering, '2026-02-16');
    $s2 = attSession($offering, '2026-02-23');
    Sanctum::actingAs($instructor);
    attMark($s1, $enrollment, true)->assertOk();
    attMark($s2, $enrollment, false)->assertOk(); // 1/2 = 50%

    // Now as the student: My Schedule carries the derived attendance.
    Sanctum::actingAs(User::query()->find($student->user_id));
    $response = test()->getJson('/api/v1/registration/schedule')->assertOk();

    expect($response->json('data.0.enrollments.0.attendance.percentage'))->toBe(50)
        ->and($response->json('data.0.enrollments.0.attendance.at_risk'))->toBeTrue();
});
