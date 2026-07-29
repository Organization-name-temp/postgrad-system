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
| My Grades (ADR-0020, ticket 04) — the Student's own record: Semester GPAs beside their
| Courses, and the CGPA prominently, closing SRS §4.7.2. Written against the /api/v1 HTTP
| seam. The Sheet sign/finalize workflow is already covered end-to-end by ticket 03's
| SheetSigningReviewTest, so here the "a Grade is on a Finalized Sheet" precondition is built
| directly (the AttendanceTest precedent of factory-built fixtures for state that is not
| itself under test), and the read surface is what is driven through HTTP.
| Self-contained helpers (mg* prefix) so a single-file run works.
*/

function mgStudent(): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);

    return Student::factory()->create(['user_id' => $user->id]);
}

/** An Offering for a fresh Course in the given Semester (or a fresh one). */
function mgOffering(?Semester $semester = null, ?Course $course = null): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => ($semester ?? Semester::factory()->create())->id,
        'course_id' => ($course ?? Course::factory()->create(['total_ch' => 3]))->id,
        'instructor_id' => User::factory()->create()->id,
    ]);
}

function mgEnroll(CourseOffering $offering, Student $student, CourseStatus $status = CourseStatus::Regular, int $creditHours = 3): Enrollment
{
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => $creditHours,
    ]);
}

/**
 * A Grade on a Sheet in the given state for the Enrollment — Finalized by default. Also
 * writes the ADR-0022 `earned` bit exactly as the real finalize transaction would (mark ≥
 * the C+ threshold), since this bypasses that controller entirely.
 */
function mgGrade(Enrollment $enrollment, float $mark, GradeSheetStatus $status = GradeSheetStatus::Finalized): Grade
{
    $sheet = GradeSheet::factory()->inStatus($status)->create([
        'course_offering_id' => $enrollment->course_offering_id,
        'reviewed_at' => $status === GradeSheetStatus::Finalized ? now() : null,
    ]);

    $grade = Grade::factory()->withMark($mark)->create([
        'grade_sheet_id' => $sheet->id,
        'enrollment_id' => $enrollment->id,
    ]);

    if ($status === GradeSheetStatus::Finalized) {
        Enrollment::query()->whereKey($enrollment->id)->update(['earned' => (float) $grade->gpa_points >= 2.5]);
    }

    return $grade;
}

function mgMyGrades()
{
    return test()->getJson('/api/v1/grading/my-grades');
}

// ---------------------------------------------------------------------------
// Visibility — nothing until the Department Head has finalized the Sheet
// ---------------------------------------------------------------------------

it('shows nothing for a mark still on a submitted, not-yet-finalized sheet', function () {
    $student = mgStudent();
    $enrollment = mgEnroll(mgOffering(), $student);
    mgGrade($enrollment, 82.5, GradeSheetStatus::Submitted);
    Sanctum::actingAs($student->user);

    mgMyGrades()->assertOk()
        ->assertJsonPath('data.cgpa', null)
        ->assertJsonPath('data.semesters', []);
});

it('renders the CGPA as a dash-worthy null before any graded course exists', function () {
    Sanctum::actingAs(mgStudent()->user);

    mgMyGrades()->assertOk()->assertJsonPath('data.cgpa', null);
});

// ---------------------------------------------------------------------------
// The finalized record — semester GPA, CGPA, and each course's facts
// ---------------------------------------------------------------------------

it('shows a finalized grade with its credit hours, letter, and whether it earned its credit', function () {
    $student = mgStudent();
    $enrollment = mgEnroll(mgOffering(), $student, creditHours: 3);
    mgGrade($enrollment, 82.5); // B, 3.00 points, earns its credit
    Sanctum::actingAs($student->user);

    $response = mgMyGrades()->assertOk();

    // A whole-number GPA round-trips through JSON as a bare integer (the GradeSheetController
    // gpa_points precedent) — the value is still the float GpaCalculator returns; JSON simply
    // carries no int/float distinction for a whole number.
    $response->assertJsonPath('data.cgpa', 3)
        ->assertJsonPath('data.semesters.0.gpa', 3)
        ->assertJsonPath('data.semesters.0.courses.0.enrollment_id', $enrollment->id)
        ->assertJsonPath('data.semesters.0.courses.0.credit_hours', 3)
        ->assertJsonPath('data.semesters.0.courses.0.grade.letter_grade', 'B')
        ->assertJsonPath('data.semesters.0.courses.0.earned', true)
        ->assertJsonPath('data.semesters.0.courses.0.superseded', false);
});

it('marks a below-C+ grade as not earning its credit while it still counts toward the CGPA', function () {
    $student = mgStudent();
    $enrollment = mgEnroll(mgOffering(), $student);
    mgGrade($enrollment, 62); // D, 1.00 points — counts toward CGPA, earns nothing

    Sanctum::actingAs($student->user);
    $response = mgMyGrades()->assertOk();

    $response->assertJsonPath('data.semesters.0.courses.0.earned', false)
        ->assertJsonPath('data.cgpa', 1);
});

it('groups courses by semester, newest semester first', function () {
    $student = mgStudent();
    $older = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $newer = Semester::factory()->create(['starts_at' => now()]);
    mgGrade(mgEnroll(mgOffering($older), $student), 82.5);
    mgGrade(mgEnroll(mgOffering($newer), $student), 91);

    Sanctum::actingAs($student->user);
    $response = mgMyGrades()->assertOk();

    expect($response->json('data.semesters'))->toHaveCount(2)
        ->and($response->json('data.semesters.0.semester.id'))->toBe($newer->id)
        ->and($response->json('data.semesters.1.semester.id'))->toBe($older->id);
});

it('shows both grades for two different electives — neither supersedes the other (FR-REG-006.4)', function () {
    $student = mgStudent();
    mgGrade(mgEnroll(mgOffering(), $student), 40); // failed elective A
    mgGrade(mgEnroll(mgOffering(), $student), 91); // passed elective B

    Sanctum::actingAs($student->user);
    $response = mgMyGrades()->assertOk();

    $letters = collect($response->json('data.semesters'))
        ->flatMap(fn (array $s): array => $s['courses'])
        ->pluck('grade.letter_grade');
    expect($letters)->toHaveCount(2)->and($letters->sort()->values()->all())->toBe(['A', 'F']);
});

// ---------------------------------------------------------------------------
// Supersession — a worse Retake still lowers the CGPA, and the record shows both
// ---------------------------------------------------------------------------

it('shows a superseded earlier attempt on the record, but excluded from the CGPA and from its own semester GPA', function () {
    $student = mgStudent();
    $course = Course::factory()->create(['total_ch' => 3]);
    $earlier = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $later = Semester::factory()->create(['starts_at' => now()]);

    $firstAttempt = mgEnroll(mgOffering($earlier, $course), $student, CourseStatus::Regular);
    mgGrade($firstAttempt, 95); // A+, 4.00 — the better original attempt

    $retake = mgEnroll(mgOffering($later, $course), $student, CourseStatus::Retake);
    mgGrade($retake, 62); // D, 1.00 — a genuinely worse Retake

    Sanctum::actingAs($student->user);
    $response = mgMyGrades()->assertOk();

    // "Latest" is unconditional: the CGPA reflects the worse retake, not the better original.
    $response->assertJsonPath('data.cgpa', 1);

    $rows = collect($response->json('data.semesters'))->flatMap(fn (array $s): array => $s['courses'])->keyBy('enrollment_id');
    expect($rows[$firstAttempt->id]['superseded'])->toBeTrue()
        ->and($rows[$firstAttempt->id]['grade']['letter_grade'])->toBe('A+') // still visible, not hidden
        ->and($rows[$retake->id]['superseded'])->toBeFalse();

    // The earlier attempt's own original semester GPA excludes it too — nothing counted there.
    $earlierSemester = collect($response->json('data.semesters'))->firstWhere('semester.id', $earlier->id);
    expect($earlierSemester['gpa'])->toBeNull();
});

// ---------------------------------------------------------------------------
// Scope — grades.view_own, the acting Student only
// ---------------------------------------------------------------------------

it('is unreachable to a user without grades.view_own', function () {
    $instructor = User::factory()->create();
    $instructor->assignRole(Role::Instructor);
    Sanctum::actingAs($instructor);

    mgMyGrades()->assertForbidden();
});

it('403s a user who holds the permission but has no Student record', function () {
    $admin = User::factory()->create();
    $admin->assignRole(Role::SystemAdmin);
    Sanctum::actingAs($admin);

    mgMyGrades()->assertForbidden();
});

it('carries no id parameter — another Student\'s record is never reachable from this endpoint', function () {
    $student = mgStudent();
    $someoneElse = mgStudent();
    mgGrade(mgEnroll(mgOffering(), $someoneElse), 91);

    Sanctum::actingAs($student->user);
    $response = mgMyGrades()->assertOk();

    expect($response->json('data.semesters'))->toBe([]);
});
