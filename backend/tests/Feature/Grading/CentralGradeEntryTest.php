<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\Enums\CourseLevel;
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
| Student Affairs' central grade entry (ticket 09). Read straight off the SRS §3.2 matrix, with
| no new permission and no ADR-0015 override: Student Affairs holds `grades.enter` but not
| `grades.finalize`, and that pair of facts means exactly one thing — SA keys marks into any
| Draft Grade Sheet (the paper-grade-sheet path a registrar's office needs) and can never sign
| one. Every SA write to someone else's Sheet is audited; an Instructor writing their own is not.
|
| Driven at the /api/v1 HTTP seam throughout, with the audit trail read back from its table (the
| Phase-2/3 precedent for asserting an audit row) — the Audit Log has no API surface of its own.
| Self-contained helpers (ce* prefix) so a single-file run works.
*/

/** Student Affairs (R05) — holds grades.enter, and by §3.2 no grades.finalize. */
function ceStudentAffairs(): User
{
    $user = User::factory()->create(['name' => 'Central Entry Clerk']);
    $user->assignRole(Role::StudentAffairs);

    return $user;
}

function ceInstructor(string $name = 'Nadia Instructor'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::Instructor);

    return $user;
}

function ceOffering(User $instructor, ?Course $course = null, ?Semester $semester = null): CourseOffering
{
    $course ??= Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);

    return CourseOffering::factory()->create([
        'semester_id' => ($semester ?? Semester::factory()->create())->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);
}

function ceEnroll(CourseOffering $offering, CourseStatus $status = CourseStatus::Regular): Enrollment
{
    $user = User::factory()->create(['name' => 'Sara Student']);
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

/** @return array<int, array<string, mixed>> */
function ceAuditRows(string $action): array
{
    return DB::table('audit_log')->where('action', $action)->get()
        ->map(fn ($row): array => (array) $row)
        ->all();
}

// ---------------------------------------------------------------------------
// The Offering picker — every Offering, not only the ones the actor teaches
// ---------------------------------------------------------------------------

it('lists every offering to a central-entry user, not only their own', function () {
    $first = ceOffering(ceInstructor(), Course::factory()->create(['code' => 'DEP501']));
    $second = ceOffering(ceInstructor('Omar Instructor'), Course::factory()->create(['code' => 'DEP502']));
    Sanctum::actingAs(ceStudentAffairs());

    $response = test()->getJson('/api/v1/grading/offerings')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and(array_column($response->json('data'), 'offering_id'))
        ->toEqualCanonicalizing([$first->id, $second->id]);
    $response->assertJsonPath('meta.scope', 'central');
});

it('names each offering\'s semester and assigned instructor on the picker', function () {
    $instructor = ceInstructor('Nadia Instructor');
    $semester = Semester::factory()->create(['term' => 'Fall', 'year' => 2026]);
    ceOffering($instructor, Course::factory()->create(['code' => 'DEP501']), $semester);
    Sanctum::actingAs(ceStudentAffairs());

    test()->getJson('/api/v1/grading/offerings')->assertOk()
        ->assertJsonPath('data.0.semester.name_en', 'Fall 2026')
        ->assertJsonPath('data.0.semester.name_ar', 'خريف 2026')
        ->assertJsonPath('data.0.instructor.id', $instructor->id)
        ->assertJsonPath('data.0.instructor.name', 'Nadia Instructor');
});

it('still scopes an instructor\'s own picker to the offerings they teach', function () {
    $instructor = ceInstructor();
    $mine = ceOffering($instructor);
    ceOffering(ceInstructor('Omar Instructor'));
    Sanctum::actingAs($instructor);

    $response = test()->getJson('/api/v1/grading/offerings')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.offering_id'))->toBe($mine->id);
    $response->assertJsonPath('meta.scope', 'own');
});

// ---------------------------------------------------------------------------
// Entry on someone else's Sheet — and the signature that stays out of reach
// ---------------------------------------------------------------------------

it('opens any offering\'s draft sheet for a central-entry user', function () {
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    Sanctum::actingAs(ceStudentAffairs());

    test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->assertOk()
        ->assertJsonPath('data.sheet.status', GradeSheetStatus::Draft->value)
        ->assertJsonPath('data.lines.0.enrollment_id', $enrollment->id);
});

it('lets a central-entry user enter and then correct a mark on someone else\'s sheet', function () {
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    Sanctum::actingAs(ceStudentAffairs());
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');

    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 82.5])
        ->assertOk()
        ->assertJsonPath('data.grade.letter_grade', 'B');
    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 91])
        ->assertOk()
        ->assertJsonPath('data.grade.letter_grade', 'A');

    test()->assertDatabaseCount('grades', 1);
});

it('lets a central-entry user declare an incomplete on someone else\'s sheet', function () {
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    Sanctum::actingAs(ceStudentAffairs());
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');

    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", [
        'completion_percentage' => 80,
        'reason' => 'Hospitalised before the final project was due.',
    ])
        ->assertOk()
        ->assertJsonPath('data.incomplete.completion_percentage', 80)
        ->assertJsonPath('data.grade', null);
});

it('lets a central-entry user key marks in but never sign — both halves of the matrix, in one place', function () {
    // Read literally, `grades.enter` without `grades.finalize` is central data entry and nothing
    // more. If either half of this ever changes, this is the test that says so.
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    Sanctum::actingAs(ceStudentAffairs());
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');

    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 82.5])->assertOk();

    test()->postJson("/api/v1/grading/sheets/{$sheetId}/sign")->assertForbidden();

    // The round never left Draft, so the Instructor is still the one who will author the
    // signature that puts these grades into the academic record.
    expect(GradeSheet::query()->find($sheetId)->status)->toBe(GradeSheetStatus::Draft);
});

it('keeps a department head out of mark entry entirely', function () {
    // R06 holds grades.finalize but no grades.enter, so the route gate refuses them before any
    // assignment check — this ticket must not widen write access as a side effect.
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    $sheet = GradeSheet::factory()->create(['course_offering_id' => $offering->id]);
    Sanctum::actingAs($head);

    test()->getJson('/api/v1/grading/offerings')->assertForbidden();
    test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->assertForbidden();
    test()->putJson("/api/v1/grading/sheets/{$sheet->id}/lines/{$enrollment->id}", ['mark' => 80])
        ->assertForbidden();

    test()->assertDatabaseCount('grades', 0);
});

it('still forbids an instructor reaching an offering they do not teach', function () {
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    $sheet = GradeSheet::factory()->create(['course_offering_id' => $offering->id]);
    Sanctum::actingAs(ceInstructor('Omar Instructor'));

    test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->assertForbidden();
    test()->putJson("/api/v1/grading/sheets/{$sheet->id}/lines/{$enrollment->id}", ['mark' => 80])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// The audit trail — central writes only
// ---------------------------------------------------------------------------

it('audits a central-entry mark on someone else\'s sheet, naming the actor and the assigned instructor', function () {
    $instructor = ceInstructor();
    $offering = ceOffering($instructor);
    $enrollment = ceEnroll($offering);
    $clerk = ceStudentAffairs();
    Sanctum::actingAs($clerk);
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');

    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 82.5])->assertOk();

    $rows = ceAuditRows('grade.entered_centrally');
    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]['actor_user_id'])->toBe($clerk->id)
        ->and($rows[0]['subject_type'])->toBe('grade_sheet')
        ->and((int) $rows[0]['subject_id'])->toBe($sheetId);

    $metadata = json_decode($rows[0]['metadata'], true);
    expect($metadata['course_offering_id'])->toBe($offering->id)
        ->and($metadata['assigned_instructor_id'])->toBe($instructor->id)
        ->and($metadata['enrollment_id'])->toBe($enrollment->id)
        ->and($metadata['mark'])->toBe(82.5);
});

it('audits a central-entry incomplete declaration too', function () {
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    Sanctum::actingAs(ceStudentAffairs());
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');

    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", [
        'completion_percentage' => 80,
        'reason' => 'Hospitalised before the final project was due.',
    ])->assertOk();

    $rows = ceAuditRows('grade.incomplete_declared_centrally');
    expect($rows)->toHaveCount(1)
        ->and(json_decode($rows[0]['metadata'], true)['completion_percentage'])->toBe(80);
});

it('never audits an instructor entering a mark on their own sheet', function () {
    // The Phase-3 precedent: the legitimately-scoped party's own action stays out of the trail.
    $instructor = ceInstructor();
    $offering = ceOffering($instructor);
    $enrollment = ceEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');

    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 82.5])->assertOk();

    expect(ceAuditRows('grade.entered_centrally'))->toBe([]);
});

it('never audits a refused write', function () {
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    $sheet = GradeSheet::factory()->create(['course_offering_id' => $offering->id]);
    Sanctum::actingAs(ceInstructor('Omar Instructor'));

    test()->putJson("/api/v1/grading/sheets/{$sheet->id}/lines/{$enrollment->id}", ['mark' => 80])
        ->assertForbidden();

    expect(ceAuditRows('grade.entered_centrally'))->toBe([]);
});

it('audits the first open of someone else\'s sheet, since that open creates the round', function () {
    $offering = ceOffering(ceInstructor());
    Sanctum::actingAs(ceStudentAffairs());

    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->assertOk()
        ->json('data.sheet.id');

    $rows = ceAuditRows('grade.sheet_opened_centrally');
    expect($rows)->toHaveCount(1)
        ->and((int) $rows[0]['subject_id'])->toBe($sheetId);

    // Re-opening is a pure read and adds nothing to the trail.
    test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->assertOk();
    expect(ceAuditRows('grade.sheet_opened_centrally'))->toHaveCount(1);
});

it('never audits an instructor opening their own sheet for the first time', function () {
    $instructor = ceInstructor();
    $offering = ceOffering($instructor);
    Sanctum::actingAs($instructor);

    test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->assertOk();

    expect(ceAuditRows('grade.sheet_opened_centrally'))->toBe([]);
});

// ---------------------------------------------------------------------------
// The line central entry does not cross: the finalized record
// ---------------------------------------------------------------------------

it('refuses a central-entry user a correction round against a finalized sheet', function () {
    // Keying a mark into a Draft round is data entry; reopening the finalized academic record is
    // the Instructor's own judgement, and this ticket widens the first without the second.
    $offering = ceOffering(ceInstructor());
    $enrollment = ceEnroll($offering);
    $finalized = GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
        'course_offering_id' => $offering->id,
    ]);
    Grade::create([
        'grade_sheet_id' => $finalized->id,
        'enrollment_id' => $enrollment->id,
        ...Grade::derivedFrom(62.0),
    ]);
    Sanctum::actingAs(ceStudentAffairs());

    test()->postJson("/api/v1/grading/sheets/{$finalized->id}/correction", [
        'enrollment_ids' => [$enrollment->id],
    ])->assertForbidden();

    test()->assertDatabaseCount('grade_sheets', 1);
});

it('still lets a central-entry user key marks into a correction round the instructor opened', function () {
    $instructor = ceInstructor();
    $offering = ceOffering($instructor);
    $enrollment = ceEnroll($offering);
    $finalized = GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create([
        'course_offering_id' => $offering->id,
    ]);
    Grade::create([
        'grade_sheet_id' => $finalized->id,
        'enrollment_id' => $enrollment->id,
        ...Grade::derivedFrom(62.0),
    ]);

    Sanctum::actingAs($instructor);
    $correctionId = test()->postJson("/api/v1/grading/sheets/{$finalized->id}/correction", [
        'enrollment_ids' => [$enrollment->id],
    ])->assertCreated()->json('data.sheet.id');

    Sanctum::actingAs(ceStudentAffairs());
    test()->getJson("/api/v1/grading/corrections/{$correctionId}")->assertOk();
    test()->putJson("/api/v1/grading/sheets/{$correctionId}/lines/{$enrollment->id}", ['mark' => 78])
        ->assertOk()
        ->assertJsonPath('data.grade.letter_grade', 'C+');

    expect(ceAuditRows('grade.entered_centrally'))->toHaveCount(1);
});
