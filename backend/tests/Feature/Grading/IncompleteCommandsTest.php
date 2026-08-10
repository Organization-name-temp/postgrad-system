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
| Ticket 05's two manually-run commands (SRS §4.7.3.3, §4.7.3.4). Both are driven at the
| highest point — command invocation → observable Notification rows via the notifications
| endpoint and Enrollment state through the API/model — never by reaching into command
| internals (the Admissions OverdueFlaggingCommandTest/InterviewReminderCommandTest precedent).
| Time is frozen so "days remaining"/"past the deadline" are deterministic.
| Self-contained helpers (ic* prefix) so a single-file run works.
*/

/**
 * A live, outstanding Incomplete whose derived deadline is `$nextSemesterStart` + 63 days —
 * built directly through the factories (the AttendanceTest/MyGradesTest precedent of
 * factory-built fixtures for state not itself under test; the declare→sign→finalize path is
 * already covered by IncompleteLifecycleTest).
 *
 * @return array{0: Incomplete, 1: Enrollment, 2: Student}
 */
function icOutstandingIncomplete(Carbon $enrollmentSemesterStart, ?Carbon $nextSemesterStart): array
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    $student = Student::factory()->create(['user_id' => $user->id]);

    $semester = Semester::factory()->create(['starts_at' => $enrollmentSemesterStart]);
    if ($nextSemesterStart !== null) {
        Semester::factory()->create(['starts_at' => $nextSemesterStart]);
    }

    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Incomplete,
        'pre_incomplete_status' => CourseStatus::Regular,
        'credit_hours' => 3,
    ]);

    $sheet = GradeSheet::factory()->create(['course_offering_id' => $offering->id]);
    $incomplete = Incomplete::factory()->create([
        'declared_on_grade_sheet_id' => $sheet->id,
        'enrollment_id' => $enrollment->id,
        'declared_by' => $offering->instructor_id,
    ]);

    return [$incomplete, $enrollment, $student];
}

function icNotificationsFor(Student $student)
{
    Sanctum::actingAs($student->user);

    return test()->getJson('/api/v1/notifications');
}

// ---------------------------------------------------------------------------
// grading:send-incomplete-reminders
// ---------------------------------------------------------------------------

it('reminds at exactly 28, 14, 7 and 1 days remaining', function () {
    foreach ([28, 14, 7, 1] as $offset) {
        Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
        $deadline = now()->copy()->addDays($offset);
        // Deadline = nextSemesterStart + 63 days, so nextSemesterStart = deadline - 63 days.
        [, , $student] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));

        $this->artisan('grading:send-incomplete-reminders')->assertSuccessful();

        icNotificationsFor($student)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'incomplete.reminder')
            ->assertJsonPath('data.0.data.days_remaining', $offset);
    }
});

it('stays silent on a day that is not one of the configured offsets', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $deadline = now()->copy()->addDays(15); // not 28/14/7/1
    [, , $student] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));

    $this->artisan('grading:send-incomplete-reminders')->assertSuccessful();

    icNotificationsFor($student)->assertOk()->assertJsonCount(0, 'data');
});

it('re-notifies on a second same-day run — no sent-marker', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $deadline = now()->copy()->addDays(7);
    [, , $student] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));

    $this->artisan('grading:send-incomplete-reminders')->assertSuccessful();
    $this->artisan('grading:send-incomplete-reminders')->assertSuccessful();

    icNotificationsFor($student)->assertOk()->assertJsonCount(2, 'data');
});

it('changes no status when reminding', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $deadline = now()->copy()->addDays(28);
    [, $enrollment] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));

    $this->artisan('grading:send-incomplete-reminders')->assertSuccessful();

    expect($enrollment->fresh()->course_status)->toBe(CourseStatus::Incomplete);
});

it('skips, without crashing or notifying, an incomplete with no next semester yet', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    [, , $student] = icOutstandingIncomplete(now()->copy()->subDays(200), null);

    $this->artisan('grading:send-incomplete-reminders')->assertSuccessful();

    icNotificationsFor($student)->assertOk()->assertJsonCount(0, 'data');
});

// ---------------------------------------------------------------------------
// grading:convert-expired-incompletes
// ---------------------------------------------------------------------------

it('leaves an incomplete alone the day before its deadline', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $deadline = now()->copy()->addDay();
    [$incomplete, $enrollment] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));

    $this->artisan('grading:convert-expired-incompletes')->assertSuccessful();

    expect($enrollment->fresh()->course_status)->toBe(CourseStatus::Incomplete)
        ->and($incomplete->fresh()->converted_at)->toBeNull();
});

it('leaves an incomplete alone on the day of its deadline — the resolution window includes it in full', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $deadline = now(); // today is the deadline day itself
    [$incomplete, $enrollment] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));

    $this->artisan('grading:convert-expired-incompletes')->assertSuccessful();

    expect($enrollment->fresh()->course_status)->toBe(CourseStatus::Incomplete)
        ->and($incomplete->fresh()->converted_at)->toBeNull();
});

it('converts to Fail Withdrawal the day after the deadline, and notifies the student', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $deadline = now()->copy()->subDay();
    [$incomplete, $enrollment, $student] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));

    $this->artisan('grading:convert-expired-incompletes')->assertSuccessful();

    $fresh = $enrollment->fresh();
    expect($fresh->course_status)->toBe(CourseStatus::FailWithdrawal)
        ->and($incomplete->fresh()->converted_at)->not->toBeNull();

    icNotificationsFor($student)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'incomplete.converted')
        ->assertJsonPath('data.0.data.enrollment_id', $enrollment->id);
});

it('skips and reports, never converting or crashing, an incomplete with no next semester yet', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    [$incomplete, $enrollment, $student] = icOutstandingIncomplete(now()->copy()->subDays(200), null);

    $this->artisan('grading:convert-expired-incompletes')
        ->assertSuccessful()
        ->expectsOutputToContain('skipped');

    expect($enrollment->fresh()->course_status)->toBe(CourseStatus::Incomplete)
        ->and($incomplete->fresh()->converted_at)->toBeNull();
    icNotificationsFor($student)->assertOk()->assertJsonCount(0, 'data');
});

it('never converts an already-resolved or already-converted incomplete again', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $deadline = now()->copy()->subDay();
    [$incomplete] = icOutstandingIncomplete(now()->copy()->subDays(200), $deadline->copy()->subDays(63));
    $incomplete->update(['converted_at' => now()->subDays(30)]);

    $this->artisan('grading:convert-expired-incompletes')->assertSuccessful();

    // Only the one (stale) converted_at timestamp — never touched a second time.
    expect($incomplete->fresh()->converted_at->toDateString())->toBe(now()->subDays(30)->toDateString());
});
