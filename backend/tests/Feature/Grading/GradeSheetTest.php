<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The Grade Sheet and mark entry (ticket 01, ADR-0021/0023). Written against the /api/v1 HTTP
| seam (real Postgres, full HTTP), the highest available point: an Instructor opens the Sheet
| for an Offering they teach — created lazily on that first open — sees one line per gradable
| Enrollment with the Student's University ID and name, and saves one mark at a time, each
| carrying the Letter Grade and GPA points the scale freezes onto it.
|
| Entry is gated on grades.enter, but authority over a *particular* Sheet is the Offering
| assignment (ADR-0023), so an Instructor reaching another's Offering is a 403. Nothing here
| signs or reviews — that is ticket 03.
| Self-contained helpers (gs* prefix) so a single-file run works.
*/

/** An instructor user (R04) — the role that holds grades.enter and teaches Offerings. */
function gsInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/** An Offering taught by the given instructor. */
function gsOffering(User $instructor, ?Course $course = null): CourseOffering
{
    $course ??= Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);

    return CourseOffering::factory()->create([
        'semester_id' => Semester::factory()->create()->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);
}

/** A fresh Student (R02) enrolled in the Offering, in the given course status. */
function gsEnroll(CourseOffering $offering, CourseStatus $status = CourseStatus::Regular, string $name = 'Sara Student'): Enrollment
{
    $user = User::factory()->create(['name' => $name]);
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

/** Open the Offering's Sheet as the acting user. */
function gsOpenSheet(CourseOffering $offering)
{
    return test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet");
}

/** Save one line's mark as the acting user. */
function gsSaveMark(int $sheetId, Enrollment $enrollment, float|string $mark)
{
    return test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => $mark]);
}

// ---------------------------------------------------------------------------
// The Instructor's offerings — the entry point
// ---------------------------------------------------------------------------

it('lists the offerings the acting instructor teaches, with their course and gradable count', function () {
    $instructor = gsInstructor();
    $course = Course::factory()->create(['code' => 'DEP501']);
    $offering = gsOffering($instructor, $course);
    gsEnroll($offering);
    gsEnroll($offering);
    gsEnroll($offering, CourseStatus::Audit); // on the roster, never a gradable line
    Sanctum::actingAs($instructor);

    $response = test()->getJson('/api/v1/grading/offerings')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.offering_id', $offering->id)
        ->assertJsonPath('data.0.gradable_count', 2)
        ->assertJsonPath('data.0.course.code', 'DEP501');
});

it('never lists another instructor\'s offerings', function () {
    $instructor = gsInstructor();
    gsOffering(gsInstructor());
    Sanctum::actingAs($instructor);

    test()->getJson('/api/v1/grading/offerings')->assertOk()->assertJsonPath('data', []);
});

it('refuses the grading surface to a user without grades.enter', function () {
    $student = User::factory()->create();
    $student->assignRole(Role::Student);
    Sanctum::actingAs($student);

    test()->getJson('/api/v1/grading/offerings')->assertForbidden();
});

it('refuses the grading surface to an unauthenticated caller', function () {
    test()->getJson('/api/v1/grading/offerings')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Opening the Sheet — lazily created, scoped by assignment
// ---------------------------------------------------------------------------

it('creates the sheet lazily on first open, in Draft', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    Sanctum::actingAs($instructor);

    expect(GradeSheet::query()->count())->toBe(0);

    gsOpenSheet($offering)->assertOk()
        ->assertJsonPath('data.sheet.status', GradeSheetStatus::Draft->value)
        ->assertJsonPath('data.sheet.kind', 'Initial')
        ->assertJsonPath('data.sheet.course_offering_id', $offering->id);

    expect(GradeSheet::query()->count())->toBe(1);
});

it('returns the same round on every later open, never a second one', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    Sanctum::actingAs($instructor);

    $first = gsOpenSheet($offering)->assertOk()->json('data.sheet.id');
    $second = gsOpenSheet($offering)->assertOk()->json('data.sheet.id');

    expect($second)->toBe($first)
        ->and(GradeSheet::query()->count())->toBe(1);
});

it('lets the database refuse a second Initial round, so two concurrent opens cannot split a sheet', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    Sanctum::actingAs($instructor);
    gsOpenSheet($offering)->assertOk();

    // The lazy open is a read-then-write and so is not atomic on its own; the partial unique
    // index is what actually makes it safe (ADR-0023: an Offering has exactly one Initial
    // round). Race it directly — this is the guard, not the controller's `firstOrCreate`.
    expect(fn () => GradeSheet::factory()->create([
        'course_offering_id' => $offering->id,
        'kind' => GradeSheetKind::Initial,
    ]))->toThrow(QueryException::class);
});

it('still lets follow-up rounds of other kinds accumulate on the same offering', function () {
    // The index is *partial*: only Initial is capped. Resolution and Correction rounds are
    // how ADR-0023's later changes reach a settled Offering, so they must remain unbounded.
    $offering = gsOffering(gsInstructor());
    GradeSheet::factory()->create(['course_offering_id' => $offering->id, 'kind' => GradeSheetKind::Initial]);
    GradeSheet::factory()->create(['course_offering_id' => $offering->id, 'kind' => GradeSheetKind::Correction]);
    GradeSheet::factory()->create(['course_offering_id' => $offering->id, 'kind' => GradeSheetKind::Correction]);

    expect(GradeSheet::query()->where('course_offering_id', $offering->id)->count())->toBe(3);
});

it('forbids an instructor opening a sheet for an offering they do not teach', function () {
    $offering = gsOffering(gsInstructor());
    Sanctum::actingAs(gsInstructor());

    gsOpenSheet($offering)->assertForbidden();

    // Nothing was created by the refused open.
    expect(GradeSheet::query()->count())->toBe(0);
});

it('404s an offering that does not exist', function () {
    Sanctum::actingAs(gsInstructor());

    test()->getJson('/api/v1/grading/offerings/999999/sheet')->assertNotFound();
});

// ---------------------------------------------------------------------------
// The Sheet's lines
// ---------------------------------------------------------------------------

it('returns one line per gradable enrollment with the student\'s university id and name', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering, name: 'Sara Student');
    Sanctum::actingAs($instructor);

    $response = gsOpenSheet($offering)->assertOk();

    expect($response->json('data.lines'))->toHaveCount(1);
    $response->assertJsonPath('data.lines.0.enrollment_id', $enrollment->id)
        ->assertJsonPath('data.lines.0.course_status', 'Regular')
        ->assertJsonPath('data.lines.0.credit_hours', 3)
        ->assertJsonPath('data.lines.0.student.name', 'Sara Student')
        ->assertJsonPath('data.lines.0.grade', null);

    expect($response->json('data.lines.0.student.university_id'))->not->toBeNull();
});

it('omits audit and withdrawn enrollments from the sheet', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $regular = gsEnroll($offering, CourseStatus::Regular);
    gsEnroll($offering, CourseStatus::Audit);
    gsEnroll($offering, CourseStatus::Withdrawn);
    Sanctum::actingAs($instructor);

    $response = gsOpenSheet($offering)->assertOk();

    expect($response->json('data.lines'))->toHaveCount(1)
        ->and($response->json('data.lines.0.enrollment_id'))->toBe($regular->id);
});

it('returns an empty line list for an offering with no gradable roster', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    gsEnroll($offering, CourseStatus::Audit);
    Sanctum::actingAs($instructor);

    gsOpenSheet($offering)->assertOk()->assertJsonPath('data.lines', []);
});

it('carries the offering\'s resolved course on the sheet', function () {
    $instructor = gsInstructor();
    $course = Course::factory()->create(['code' => 'DEP502', 'name_en' => 'Distributed Systems']);
    $offering = gsOffering($instructor, $course);
    Sanctum::actingAs($instructor);

    gsOpenSheet($offering)->assertOk()
        ->assertJsonPath('data.course.code', 'DEP502')
        ->assertJsonPath('data.course.name_en', 'Distributed Systems');
});

// ---------------------------------------------------------------------------
// Mark entry — one line at a time, letter and points frozen on the Grade
// ---------------------------------------------------------------------------

it('saves one mark and derives its letter grade and gpa points', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $enrollment, 82.5)->assertOk()
        ->assertJsonPath('data.enrollment_id', $enrollment->id)
        ->assertJsonPath('data.grade.mark', 82.5)
        ->assertJsonPath('data.grade.letter_grade', 'B')
        ->assertJsonPath('data.grade.gpa_points', 3);

    // Frozen on the row, not recomputed on read.
    test()->assertDatabaseHas('grades', [
        'grade_sheet_id' => $sheetId,
        'enrollment_id' => $enrollment->id,
        'letter_grade' => 'B',
    ]);
});

it('reads a saved mark back on the sheet', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $enrollment, 95)->assertOk();

    gsOpenSheet($offering)->assertOk()
        ->assertJsonPath('data.lines.0.grade.mark', 95)
        ->assertJsonPath('data.lines.0.grade.letter_grade', 'A+')
        ->assertJsonPath('data.lines.0.grade.gpa_points', 4);
});

it('saves each line on its own, so a partly-graded roster survives', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $first = gsEnroll($offering);
    $second = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $first, 71)->assertOk();

    $lines = collect(gsOpenSheet($offering)->json('data.lines'))->keyBy('enrollment_id');

    expect($lines[$first->id]['grade']['letter_grade'])->toBe('C')
        ->and($lines[$second->id]['grade'])->toBeNull();
});

it('corrects a mark by re-saving the line rather than adding a second grade', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $enrollment, 55)->assertOk()->assertJsonPath('data.grade.letter_grade', 'F');
    gsSaveMark($sheetId, $enrollment, 88)->assertOk()->assertJsonPath('data.grade.letter_grade', 'B+');

    test()->assertDatabaseCount('grades', 1);
});

it('records the two extremes of the scale', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $bottom = gsEnroll($offering);
    $top = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $bottom, 0)->assertOk()
        ->assertJsonPath('data.grade.letter_grade', 'F')
        ->assertJsonPath('data.grade.gpa_points', 0);
    gsSaveMark($sheetId, $top, 100)->assertOk()
        ->assertJsonPath('data.grade.letter_grade', 'A+')
        ->assertJsonPath('data.grade.gpa_points', 4);
});

// ---------------------------------------------------------------------------
// Mark validation
// ---------------------------------------------------------------------------

it('rejects a mark above 100 with a 422 naming the field', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $enrollment, 100.01)
        ->assertStatus(422)
        ->assertJsonValidationErrors('mark');

    test()->assertDatabaseCount('grades', 0);
});

it('rejects a negative mark with a 422 naming the field', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $enrollment, -1)->assertStatus(422)->assertJsonValidationErrors('mark');
});

it('rejects a non-numeric mark', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $enrollment, 'ninety')->assertStatus(422)->assertJsonValidationErrors('mark');
});

it('rejects a mark with more than two decimal places', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $enrollment, 82.555)->assertStatus(422)->assertJsonValidationErrors('mark');
});

it('localises the out-of-range message into Arabic', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    $english = gsSaveMark($sheetId, $enrollment, 120)->json('errors.mark.0');

    $arabic = test()->withHeader('Accept-Language', 'ar')
        ->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 120])
        ->assertStatus(422)
        ->json('errors.mark.0');

    expect($english)->toBe(__('grading.mark.out_of_range', [], 'en'))
        ->and($arabic)->toBe(__('grading.mark.out_of_range', [], 'ar'))
        ->and($arabic)->not->toBe($english);
});

// ---------------------------------------------------------------------------
// Mark-entry authorisation and line membership
// ---------------------------------------------------------------------------

it('forbids marking a line on an offering the acting user does not teach', function () {
    $owner = gsInstructor();
    $offering = gsOffering($owner);
    $enrollment = gsEnroll($offering);
    Sanctum::actingAs($owner);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    Sanctum::actingAs(gsInstructor());
    gsSaveMark($sheetId, $enrollment, 80)->assertForbidden();

    test()->assertDatabaseCount('grades', 0);
});

it('404s a mark for an enrollment that is not a gradable line of this sheet', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $audit = gsEnroll($offering, CourseStatus::Audit);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($offering)->json('data.sheet.id');

    gsSaveMark($sheetId, $audit, 80)->assertNotFound();
});

it('404s a mark for an enrollment on a different offering', function () {
    $instructor = gsInstructor();
    $mine = gsOffering($instructor);
    $other = gsOffering($instructor);
    $strangerLine = gsEnroll($other);
    Sanctum::actingAs($instructor);
    $sheetId = gsOpenSheet($mine)->json('data.sheet.id');

    gsSaveMark($sheetId, $strangerLine, 80)->assertNotFound();
});

it('409s a mark on a sheet that is no longer open — the state guard ticket 03 relies on', function () {
    $instructor = gsInstructor();
    $offering = gsOffering($instructor);
    $enrollment = gsEnroll($offering);
    // Submitted is unreachable through the API until ticket 03 builds the signature, so the
    // round is put in that state directly to prove the write guard is really enforced.
    $sheet = GradeSheet::factory()->inStatus(GradeSheetStatus::Submitted)->create([
        'course_offering_id' => $offering->id,
    ]);
    Sanctum::actingAs($instructor);

    gsSaveMark($sheet->id, $enrollment, 80)->assertStatus(409);

    test()->assertDatabaseCount('grades', 0);
});
