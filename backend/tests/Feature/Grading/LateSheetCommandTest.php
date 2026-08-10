<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| `grading:flag-late-sheets` (ticket 09) — the Phase-2 SLA-aging pattern applied to grade
| submission: an Offering whose Semester's grade-submission deadline has passed and which has no
| Finalized Sheet gets its Instructor and its routed Department Head nudged. It **changes
| nothing**. A hard gate was considered and rejected — an Instructor locked out of submitting
| cannot deliver grades at all, and no SRS criterion asks for one.
|
| Driven at the highest point, the Admissions OverdueFlaggingCommandTest precedent: the command
| is invoked with artisan() and the result asserted as Notification rows read back through the
| notifications endpoint, never by reaching into the command's internals. Time is frozen so
| "past the deadline" is deterministic.
| Self-contained helpers (ls* prefix) so a single-file run works.
*/

function lsInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/**
 * An Offering in a Semester whose grade-submission deadline is `$deadline`, taught by
 * `$instructor`, in a Department that `$head` (when given) heads.
 */
function lsOffering(User $instructor, Carbon $deadline, ?Department $department = null): CourseOffering
{
    $semester = Semester::factory()->create([
        'ends_at' => $deadline->copy()->subWeek(),
        'grade_submission_deadline_at' => $deadline,
    ]);
    $course = Course::factory()->create(['department_id' => $department?->id]);

    return CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);
}

function lsNotificationsFor(User $user)
{
    Sanctum::actingAs($user);

    return test()->getJson('/api/v1/notifications');
}

// ---------------------------------------------------------------------------
// Flagging
// ---------------------------------------------------------------------------

it('notifies the instructor and the routed department head about an offering past its deadline with no finalized sheet', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $department = Department::factory()->create();
    $head = DepartmentHeadAssignment::factory()->create(['department_id' => $department->id])->user;
    $instructor = lsInstructor();
    $offering = lsOffering($instructor, now()->copy()->subWeek(), $department);

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    lsNotificationsFor($instructor)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'grades.overdue')
        ->assertJsonPath('data.0.data.course_offering_id', $offering->id);
    lsNotificationsFor($head)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'grades.overdue')
        ->assertJsonPath('data.0.data.course_offering_id', $offering->id);
});

it('changes nothing at all — no sheet is created, opened or moved', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $offering = lsOffering(lsInstructor(), now()->copy()->subWeek());
    $draft = GradeSheet::factory()->create(['course_offering_id' => $offering->id]);

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    expect(GradeSheet::query()->count())->toBe(1)
        ->and($draft->fresh()->status)->toBe(GradeSheetStatus::Draft)
        ->and($draft->fresh()->signed_at)->toBeNull();
});

it('flags an offering whose sheet is still only submitted — not finalized is not delivered', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $instructor = lsInstructor();
    $offering = lsOffering($instructor, now()->copy()->subWeek());
    GradeSheet::factory()->inStatus(GradeSheetStatus::Submitted)->create([
        'course_offering_id' => $offering->id,
    ]);

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    lsNotificationsFor($instructor)->assertOk()->assertJsonCount(1, 'data');
});

it('reaches the university-wide head for a department-less preparatory offering', function () {
    // Course.department_id is nullable (ADR-0010) and so is the Head assignment's, so NULL
    // matches NULL with no special case — the same routing rule the signature notification uses.
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $wideHead = DepartmentHeadAssignment::factory()->create(['department_id' => null])->user;
    lsOffering(lsInstructor(), now()->copy()->subWeek(), department: null);

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    lsNotificationsFor($wideHead)->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'grades.overdue');
});

// ---------------------------------------------------------------------------
// Left alone
// ---------------------------------------------------------------------------

it('leaves an offering with a finalized sheet alone', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $department = Department::factory()->create();
    $head = DepartmentHeadAssignment::factory()->create(['department_id' => $department->id])->user;
    $instructor = lsInstructor();
    $offering = lsOffering($instructor, now()->copy()->subWeek(), $department);
    GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
        'course_offering_id' => $offering->id,
    ]);

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    lsNotificationsFor($instructor)->assertOk()->assertJsonCount(0, 'data');
    lsNotificationsFor($head)->assertOk()->assertJsonCount(0, 'data');
});

it('counts a finalized follow-up round as delivered, whatever kind it is', function () {
    // An Offering settled by an Incomplete resolution or a Correction round is not late; only
    // "no finalized round at all" is (ADR-0023 — an Offering accumulates rounds).
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $instructor = lsInstructor();
    $offering = lsOffering($instructor, now()->copy()->subWeek());
    GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
        'course_offering_id' => $offering->id,
        'kind' => GradeSheetKind::Correction,
    ]);

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    lsNotificationsFor($instructor)->assertOk()->assertJsonCount(0, 'data');
});

it('leaves an offering whose deadline has not passed alone, including on the deadline day itself', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $ahead = lsInstructor();
    $onTheDay = lsInstructor();
    lsOffering($ahead, now()->copy()->addWeek());
    lsOffering($onTheDay, now()->copy());

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    lsNotificationsFor($ahead)->assertOk()->assertJsonCount(0, 'data');
    lsNotificationsFor($onTheDay)->assertOk()->assertJsonCount(0, 'data');
});

it('re-notifies on a second run — a hand-run flag carries no sent-marker', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    $instructor = lsInstructor();
    lsOffering($instructor, now()->copy()->subWeek());

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();
    test()->artisan('grading:flag-late-sheets')->assertSuccessful();

    lsNotificationsFor($instructor)->assertOk()->assertJsonCount(2, 'data');
});

it('reports how many offerings it flagged', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));
    lsOffering(lsInstructor(), now()->copy()->subWeek());

    test()->artisan('grading:flag-late-sheets')
        ->assertSuccessful()
        ->expectsOutputToContain('1');
});

it('succeeds silently when nothing is late', function () {
    Carbon::setTestNow(Carbon::parse('2026-01-01 00:00:00'));

    test()->artisan('grading:flag-late-sheets')->assertSuccessful();
});
