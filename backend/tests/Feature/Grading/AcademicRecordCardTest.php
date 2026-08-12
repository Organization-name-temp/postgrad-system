<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| My Academic Record Card (phase 4.5, ticket 01) — the first Dashboard Card endpoint, and
| therefore the one that proves the whole API contract for every Card after it: read-only,
| owned by the module that owns the data, independently authorized, and returning **typed
| domain values** (numbers, ids, enum values) rather than pre-rendered display strings.
|
| The figures themselves are ADR-0020's on-demand derivation, already covered by
| GradingRecordsTest and MyGradesTest at the arithmetic seam — what is asserted here is that
| the Card reads *that* derivation (same supersession rule, same null-not-zero rule) and who
| is allowed to read it. Self-contained helpers (arc* prefix) so a single-file run works.
*/

function arcStudent(): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);

    return Student::factory()->create(['user_id' => $user->id]);
}

/** An Offering for a fresh Course in the given Semester (or a fresh one). */
function arcOffering(?Semester $semester = null): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => ($semester ?? Semester::factory()->create())->id,
        'course_id' => Course::factory()->create(['total_ch' => 3])->id,
        'instructor_id' => User::factory()->create()->id,
    ]);
}

function arcEnroll(CourseOffering $offering, Student $student, int $creditHours = 3): Enrollment
{
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => $creditHours,
    ]);
}

/**
 * A Finalized Grade for the Enrollment, writing the ADR-0022 `earned` bit exactly as the real
 * finalize transaction would (mark ≥ the C+ threshold) — this bypasses that controller, which
 * ticket 03's SheetSigningReviewTest already drives end-to-end.
 */
function arcGrade(Enrollment $enrollment, float $mark): Grade
{
    $sheet = GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
        'course_offering_id' => $enrollment->course_offering_id,
        'reviewed_at' => now(),
    ]);

    $grade = Grade::factory()->withMark($mark)->create([
        'grade_sheet_id' => $sheet->id,
        'enrollment_id' => $enrollment->id,
    ]);

    Enrollment::query()->whereKey($enrollment->id)->update(['earned' => (float) $grade->gpa_points >= 2.5]);

    return $grade;
}

function arcCard()
{
    return test()->getJson('/api/v1/grading/cards/academic-record');
}

// ---------------------------------------------------------------------------
// The payload
// ---------------------------------------------------------------------------

it('returns the CGPA, the latest semester GPA and the earned credit hours as typed values', function () {
    $student = arcStudent();
    $older = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $newer = Semester::factory()->create(['starts_at' => now()]);

    arcGrade(arcEnroll(arcOffering($older), $student), 82.5); // B — 3.00 points over 3 CH, earned
    arcGrade(arcEnroll(arcOffering($newer), $student), 92.0); // A — 4.00 points over 3 CH, earned

    Sanctum::actingAs($student->user);
    $response = arcCard()->assertOk();

    // 3.50 over both terms; the newest term alone is the 4.00. A whole-number GPA round-trips
    // through JSON as a bare integer (the MyGrades precedent) — JSON carries no int/float
    // distinction for a whole number.
    $response->assertJsonPath('data.cgpa', 3.5)
        ->assertJsonPath('data.latest_semester_gpa', 4)
        ->assertJsonPath('data.earned_credit_hours', 6)
        // The Semester travels as domain data — id, term, year and both names — never as a
        // pre-rendered label, so the SPA renders it bilingually (the Notifications rule).
        ->assertJsonPath('data.latest_semester.id', $newer->id)
        ->assertJsonPath('data.latest_semester.term', $newer->term->value)
        ->assertJsonPath('data.latest_semester.year', $newer->year)
        ->assertJsonPath('data.latest_semester.name_en', $newer->name_en);
});

it('reads the same supersession rule the record does, so a retake never double-counts', function () {
    $student = arcStudent();
    $course = Course::factory()->create(['total_ch' => 3]);
    $older = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $newer = Semester::factory()->create(['starts_at' => now()]);

    $first = CourseOffering::factory()->create([
        'semester_id' => $older->id, 'course_id' => $course->id, 'instructor_id' => User::factory()->create()->id,
    ]);
    $retake = CourseOffering::factory()->create([
        'semester_id' => $newer->id, 'course_id' => $course->id, 'instructor_id' => User::factory()->create()->id,
    ]);

    arcGrade(arcEnroll($first, $student), 62.0);  // D — earns nothing
    arcGrade(arcEnroll($retake, $student), 82.5); // B — supersedes it

    Sanctum::actingAs($student->user);

    // Only the Retake counts: 3.00, not the 2.00 average of both, and the superseded D never
    // adds its credit hours to the earned total.
    arcCard()->assertOk()
        ->assertJsonPath('data.cgpa', 3)
        ->assertJsonPath('data.earned_credit_hours', 3);
});

it('excludes credit hours a grade below the pass threshold never earned', function () {
    $student = arcStudent();
    arcGrade(arcEnroll(arcOffering(), $student), 62.0); // D — counts toward the CGPA, earns nothing

    Sanctum::actingAs($student->user);

    arcCard()->assertOk()
        ->assertJsonPath('data.cgpa', 1)
        ->assertJsonPath('data.earned_credit_hours', 0);
});

// ---------------------------------------------------------------------------
// The empty record — a dash, never 0.00
// ---------------------------------------------------------------------------

it('returns a null CGPA for a student with no CGPA-counting graded enrollment', function () {
    Sanctum::actingAs(arcStudent()->user);

    arcCard()->assertOk()
        ->assertJsonPath('data.cgpa', null)
        ->assertJsonPath('data.latest_semester', null)
        ->assertJsonPath('data.latest_semester_gpa', null)
        ->assertJsonPath('data.earned_credit_hours', 0);
});

it('ignores a mark still on a sheet the department head has not finalized', function () {
    $student = arcStudent();
    $enrollment = arcEnroll(arcOffering(), $student);
    $sheet = GradeSheet::factory()->inStatus(GradeSheetStatus::Submitted)->create([
        'course_offering_id' => $enrollment->course_offering_id,
    ]);
    Grade::factory()->withMark(92.0)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);

    Sanctum::actingAs($student->user);

    arcCard()->assertOk()->assertJsonPath('data.cgpa', null);
});

// ---------------------------------------------------------------------------
// Authorization — the server decides, not the client's choice of what to request
// ---------------------------------------------------------------------------

it('refuses a user who does not hold grades.view_own', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(Role::Instructor);
    Sanctum::actingAs($instructor);

    arcCard()->assertForbidden();
});

it('refuses an administrator who inherits the permission but holds no student anchor', function () {
    // R09 inherits every permission (ADR-0008) and has no Student record to read — the exact
    // case that makes this Card gate on the **Role** in the SPA rather than the Permission.
    $admin = User::factory()->create();
    $admin->assignRole(Role::SystemAdmin);
    Sanctum::actingAs($admin);

    arcCard()->assertForbidden();
});

it('refuses an unauthenticated request', function () {
    arcCard()->assertUnauthorized();
});
