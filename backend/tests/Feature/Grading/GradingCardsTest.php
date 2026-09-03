<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
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
| The two Grading Dashboard Cards (phase 4.5, ticket 05) — the grading round from both ends:
| My Grade Sheets (the Instructor's current teaching term and what it owes) and Sheets Awaiting
| Review (the Department Head's queue).
|
| Both read derivations already covered at their own seams — the Grade-Submission Deadline flag by
| LateSheetCommandTest, the Head↔Department routing by SheetSigningReviewTest — so what is
| asserted here is that the Cards *read* those rules (the flag blocks nothing; a university-wide
| Head reaches the department-less Preparatory Sheets and a Department-scoped Head does not) and
| who is allowed to read them. Self-contained helpers (gc* prefix) so a single-file run works.
*/

function gcInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/** A Department Head assigned to `$department` — or university-wide when `true` is passed. */
function gcDeptHead(int|true|null $department): User
{
    $assignment = $department === true
        ? DepartmentHeadAssignment::factory()->universityWide()->create()
        : DepartmentHeadAssignment::factory()->create(['department_id' => $department]);

    return $assignment->user;
}

/** An Offering in `$semester` taught by `$instructor`, for a Course in `$departmentId`. */
function gcOffering(User $instructor, Semester $semester, ?int $departmentId = null): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => Course::factory()->create(['department_id' => $departmentId, 'total_ch' => 3])->id,
        'instructor_id' => $instructor->id,
    ]);
}

/** The Offering's Initial round, in the given status — the state the Cards read. */
function gcSheet(CourseOffering $offering, GradeSheetStatus $status): GradeSheet
{
    return GradeSheet::factory()->inStatus($status)->create([
        'course_offering_id' => $offering->id,
        'signed_at' => now(),
    ]);
}

function gcSheetsCard()
{
    return test()->getJson('/api/v1/grading/cards/grade-sheets');
}

function gcReviewCard()
{
    return test()->getJson('/api/v1/grading/cards/sheets-awaiting-review');
}

// ---------------------------------------------------------------------------
// My Grade Sheets — the teaching load and what it owes
// ---------------------------------------------------------------------------

it('shows this term’s offerings, the count not yet finalized, and links into each sheet', function () {
    $instructor = gcInstructor();
    $semester = Semester::factory()->running()->create();
    $graded = gcOffering($instructor, $semester);
    $pending = gcOffering($instructor, $semester);
    gcSheet($graded, GradeSheetStatus::Finalized);
    gcSheet($pending, GradeSheetStatus::Draft);

    Sanctum::actingAs($instructor);
    $response = gcSheetsCard()->assertOk();

    $response->assertJsonPath('data.semester.id', $semester->id)
        ->assertJsonPath('data.semester.name_en', $semester->name_en)
        ->assertJsonPath('data.offering_count', 2)
        ->assertJsonPath('data.unfinalized_count', 1)
        ->assertJsonCount(2, 'data.offerings')
        // `own`, not `central`: an Instructor holds grades.finalize, so the list is theirs alone.
        ->assertJsonPath('meta.scope', 'own');

    // Each row carries the Offering id the Card links its Sheet through, and the round's state as
    // an enum value rather than a pre-rendered label.
    $rows = collect($response->json('data.offerings'))->keyBy('offering_id');
    expect($rows[$graded->id]['sheet_status'])->toBe(GradeSheetStatus::Finalized->value)
        ->and($rows[$pending->id]['sheet_status'])->toBe(GradeSheetStatus::Draft->value);
});

it('reports an unstarted round as a null status rather than omitting the offering', function () {
    $instructor = gcInstructor();
    $semester = Semester::factory()->running()->create();
    $offering = gcOffering($instructor, $semester);

    Sanctum::actingAs($instructor);

    gcSheetsCard()->assertOk()
        ->assertJsonPath('data.offering_count', 1)
        ->assertJsonPath('data.unfinalized_count', 1)
        ->assertJsonPath('data.offerings.0.offering_id', $offering->id)
        ->assertJsonPath('data.offerings.0.sheet_status', null);
});

it('shows the newest unfinalized follow-up round as the actionable sheet', function () {
    $instructor = gcInstructor();
    $semester = Semester::factory()->running()->create();
    $offering = gcOffering($instructor, $semester);

    GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
        'course_offering_id' => $offering->id,
        'kind' => GradeSheetKind::Initial,
        'created_at' => now()->subDay(),
    ]);
    GradeSheet::factory()->inStatus(GradeSheetStatus::Draft)->create([
        'course_offering_id' => $offering->id,
        'kind' => GradeSheetKind::Correction,
        'created_at' => now(),
    ]);

    Sanctum::actingAs($instructor);

    gcSheetsCard()->assertOk()
        ->assertJsonPath('data.unfinalized_count', 1)
        ->assertJsonPath('data.offerings.0.sheet_status', GradeSheetStatus::Draft->value);
});

it('reads the current teaching term from the offerings, so a finished term is still shown', function () {
    // The case a running-Semester rule would get wrong: grades are keyed in *after* a term ends,
    // when no Semester is running and the marks are still owed.
    $instructor = gcInstructor();
    $older = Semester::factory()->ended()->create(['starts_at' => now()->subYear()]);
    $latest = Semester::factory()->ended()->create();
    gcOffering($instructor, $older);
    $current = gcOffering($instructor, $latest);

    Sanctum::actingAs($instructor);

    gcSheetsCard()->assertOk()
        ->assertJsonPath('data.semester.id', $latest->id)
        ->assertJsonPath('data.offering_count', 1)
        ->assertJsonPath('data.offerings.0.offering_id', $current->id);
});

it('flags an offering past the grade-submission deadline with no finalized sheet', function () {
    $instructor = gcInstructor();
    $semester = Semester::factory()->ended()->create(); // deadline 10 weeks ago
    $offering = gcOffering($instructor, $semester);
    gcSheet($offering, GradeSheetStatus::Submitted);

    Sanctum::actingAs($instructor);

    gcSheetsCard()->assertOk()
        ->assertJsonPath('data.overdue_count', 1)
        ->assertJsonPath('data.offerings.0.is_overdue', true)
        ->assertJsonPath(
            'data.grade_submission_deadline_at',
            $semester->grade_submission_deadline_at->toIso8601String(),
        );
});

it('does not flag a finalized offering past the deadline, and blocks nothing either way', function () {
    $instructor = gcInstructor();
    $semester = Semester::factory()->ended()->create();
    $offering = gcOffering($instructor, $semester);
    gcSheet($offering, GradeSheetStatus::Finalized);

    Sanctum::actingAs($instructor);

    gcSheetsCard()->assertOk()
        ->assertJsonPath('data.overdue_count', 0)
        ->assertJsonPath('data.offerings.0.is_overdue', false);

    // The flag is a nudge, not a gate: the Sheet behind it is still reachable and still writable.
    test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->assertOk();
});

it('returns a null semester for an instructor who teaches nothing this term', function () {
    $instructor = gcInstructor();
    // Only a term still ahead — nothing is owed yet, so there is no current teaching term.
    gcOffering($instructor, Semester::factory()->upcoming()->create());

    Sanctum::actingAs($instructor);

    gcSheetsCard()->assertOk()
        ->assertJsonPath('data.semester', null)
        ->assertJsonPath('data.offering_count', 0)
        ->assertJsonPath('data.offerings', []);
});

it('gives a central-entry user every offering, as their grade sheets screen already does', function () {
    // Student Affairs holds grades.enter without grades.finalize (§3.2): grades arriving on paper
    // belong to Offerings the records office does not teach.
    $semester = Semester::factory()->running()->create();
    gcOffering(gcInstructor(), $semester);

    actingAsRole(Role::StudentAffairs);

    gcSheetsCard()->assertOk()
        ->assertJsonPath('meta.scope', 'central')
        ->assertJsonPath('data.offering_count', 1);
});

it('refuses a user who does not hold grades.enter', function () {
    // A Department Head holds grades.finalize but never grades.enter (§3.2).
    Sanctum::actingAs(gcDeptHead(null));

    gcSheetsCard()->assertForbidden();
});

it('refuses an unauthenticated request for the grade sheets card', function () {
    gcSheetsCard()->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Sheets Awaiting Review — the Department Head's queue
// ---------------------------------------------------------------------------

it('counts the submitted sheets routed to the acting head, with the oldest few', function () {
    $department = Department::factory()->create();
    $head = gcDeptHead($department->id);
    $semester = Semester::factory()->running()->create();
    $offering = gcOffering(gcInstructor(), $semester, $department->id);
    $sheet = gcSheet($offering, GradeSheetStatus::Submitted);

    Sanctum::actingAs($head);

    gcReviewCard()->assertOk()
        ->assertJsonPath('data.pending_count', 1)
        ->assertJsonCount(1, 'data.sheets')
        ->assertJsonPath('data.sheets.0.id', (int) $sheet->getKey())
        ->assertJsonPath('data.sheets.0.course_offering_id', $offering->id)
        // The Offering travels as domain data — its Course, bilingual — never a rendered label.
        ->assertJsonPath('data.sheets.0.course.id', $offering->course_id);
});

it('counts only the acting head’s own department, never another head’s queue', function () {
    $mine = Department::factory()->create();
    $theirs = Department::factory()->create();
    $head = gcDeptHead($mine->id);
    gcDeptHead($theirs->id);
    $semester = Semester::factory()->running()->create();

    gcSheet(gcOffering(gcInstructor(), $semester, $mine->id), GradeSheetStatus::Submitted);
    gcSheet(gcOffering(gcInstructor(), $semester, $theirs->id), GradeSheetStatus::Submitted);

    Sanctum::actingAs($head);

    gcReviewCard()->assertOk()->assertJsonPath('data.pending_count', 1);
});

it('includes the department-less preparatory sheets for a university-wide head', function () {
    // Course.department_id is nullable (ADR-0010 — the shared Preparatory courses), and null is a
    // scope rather than a missing value: it is the university-wide Head's.
    $head = gcDeptHead(true);
    $semester = Semester::factory()->running()->create();
    gcSheet(gcOffering(gcInstructor(), $semester, null), GradeSheetStatus::Submitted);

    Sanctum::actingAs($head);

    gcReviewCard()->assertOk()->assertJsonPath('data.pending_count', 1);
});

it('excludes the preparatory sheets from a department-scoped head’s card', function () {
    // The other half of the same rule: the two scopes are disjoint, so a Department's Head does
    // not stand in for the Preparatory courses.
    $department = Department::factory()->create();
    $head = gcDeptHead($department->id);
    $semester = Semester::factory()->running()->create();
    gcSheet(gcOffering(gcInstructor(), $semester, null), GradeSheetStatus::Submitted);

    Sanctum::actingAs($head);

    gcReviewCard()->assertOk()->assertJsonPath('data.pending_count', 0);
});

it('counts a department with no head assigned onto nobody’s card, without erroring', function () {
    $unheaded = Department::factory()->create();
    $head = gcDeptHead(Department::factory()->create()->id);
    $semester = Semester::factory()->running()->create();
    gcSheet(gcOffering(gcInstructor(), $semester, $unheaded->id), GradeSheetStatus::Submitted);

    Sanctum::actingAs($head);

    gcReviewCard()->assertOk()
        ->assertJsonPath('data.pending_count', 0)
        ->assertJsonPath('data.sheets', []);
});

it('counts only submitted rounds, not drafts or sheets already finalized', function () {
    $department = Department::factory()->create();
    $head = gcDeptHead($department->id);
    $semester = Semester::factory()->running()->create();

    gcSheet(gcOffering(gcInstructor(), $semester, $department->id), GradeSheetStatus::Draft);
    gcSheet(gcOffering(gcInstructor(), $semester, $department->id), GradeSheetStatus::Finalized);

    Sanctum::actingAs($head);

    gcReviewCard()->assertOk()->assertJsonPath('data.pending_count', 0);
});

it('refuses an instructor who shares the permission but is not a department head', function () {
    // Instructor and Department Head both hold grades.finalize (§3.2), but review is R06 work.
    $instructor = gcInstructor();
    $department = Department::factory()->create();
    gcDeptHead($department->id);
    $semester = Semester::factory()->running()->create();
    gcSheet(gcOffering($instructor, $semester, $department->id), GradeSheetStatus::Submitted);

    Sanctum::actingAs($instructor);

    gcReviewCard()->assertForbidden();
});

it('refuses a user who does not hold grades.finalize', function () {
    // Student Affairs holds grades.enter but never grades.finalize (§3.2).
    actingAsRole(Role::StudentAffairs);

    gcReviewCard()->assertForbidden();
});

it('refuses an unauthenticated request for the review card', function () {
    gcReviewCard()->assertUnauthorized();
});
