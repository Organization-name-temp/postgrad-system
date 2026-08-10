<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Ticket 06 — Correction rounds (ADR-0023). A finalized sheet is never reopened; an Instructor
| starts a fresh Correction round for only the lines they are changing, the Department Head
| reviews it through the same Submitted -> Finalized path, and the latest finalized Grade on
| the Enrollment becomes current while the earlier Grade stays visible as history. Written
| against the /api/v1 HTTP seam with real DB state and role gates.
| Self-contained helpers (cr* prefix) so a single-file run works.
*/

function crInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

function crDeptHead(int $departmentId): User
{
    return DepartmentHeadAssignment::factory()->create(['department_id' => $departmentId])->user;
}

function crStudent(string $name = 'Sara Student'): Student
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::Student);

    return Student::factory()->create(['user_id' => $user->id]);
}

function crOffering(User $instructor, Semester $semester, Course $course): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);
}

function crEnroll(CourseOffering $offering, Student $student, CourseStatus $status = CourseStatus::Regular): Enrollment
{
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => 3,
    ]);
}

function crOpenInitial(CourseOffering $offering): int
{
    return test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');
}

function crSave(int $sheetId, Enrollment $enrollment, float $mark)
{
    return test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => $mark]);
}

function crSign(int $sheetId)
{
    return test()->postJson("/api/v1/grading/sheets/{$sheetId}/sign");
}

function crFinalize(int $sheetId)
{
    return test()->postJson("/api/v1/grading/department-head/sheets/{$sheetId}/finalize");
}

function crStartCorrection(int $sheetId, array $enrollmentIds)
{
    return test()->postJson("/api/v1/grading/sheets/{$sheetId}/correction", ['enrollment_ids' => $enrollmentIds]);
}

function crOpenCorrection(int $sheetId)
{
    return test()->getJson("/api/v1/grading/corrections/{$sheetId}");
}

function crMyGrades()
{
    return test()->getJson('/api/v1/grading/my-grades');
}

it('opens a correction round for only the selected lines and leaves the original finalized sheet untouched', function () {
    $department = Department::factory()->create();
    $instructor = crInstructor();
    $head = crDeptHead($department->id);
    $semester = Semester::factory()->create(['starts_at' => now()->subMonth()]);
    $course = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3]);
    $offering = crOffering($instructor, $semester, $course);
    $studentA = crStudent('Sara Student');
    $studentB = crStudent('Omar Student');
    $lineA = crEnroll($offering, $studentA);
    $lineB = crEnroll($offering, $studentB);

    Sanctum::actingAs($instructor);
    $initialSheetId = crOpenInitial($offering);
    crSave($initialSheetId, $lineA, 82.5)->assertOk();
    crSave($initialSheetId, $lineB, 91)->assertOk();
    crSign($initialSheetId)->assertOk();

    Sanctum::actingAs($head);
    crFinalize($initialSheetId)->assertOk();

    Sanctum::actingAs($instructor);
    $response = crStartCorrection($initialSheetId, [$lineA->id])->assertCreated();
    $correctionSheetId = $response->json('data.sheet.id');

    $response->assertJsonPath('data.sheet.kind', 'Correction')
        ->assertJsonPath('data.lines.0.enrollment_id', $lineA->id)
        ->assertJsonPath('data.lines.0.grade', null)
        ->assertJsonPath('data.lines.0.grade_history.0.letter_grade', 'B');
    expect($response->json('data.lines'))->toHaveCount(1);

    expect(GradeSheet::find($initialSheetId)->status)->toBe(GradeSheetStatus::Finalized)
        ->and(Grade::query()->where('grade_sheet_id', $initialSheetId)->count())->toBe(2)
        ->and(GradeSheet::find($correctionSheetId)->status)->toBe(GradeSheetStatus::Draft);
});

it('runs a correction through the same sign and finalize path, making the corrected grade current and visible as history', function () {
    $department = Department::factory()->create();
    $instructor = crInstructor();
    $head = crDeptHead($department->id);
    $student = crStudent();
    $earlierSemester = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $laterSemester = Semester::factory()->create(['starts_at' => now()->subMonth()]);
    $courseA = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3, 'code' => 'DEP501']);
    $courseB = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3, 'code' => 'DEP502']);
    $offeringA = crOffering($instructor, $earlierSemester, $courseA);
    $offeringB = crOffering($instructor, $laterSemester, $courseB);
    $enrollmentA = crEnroll($offeringA, $student);
    $enrollmentB = crEnroll($offeringB, $student);

    Sanctum::actingAs($instructor);
    $sheetA = crOpenInitial($offeringA);
    crSave($sheetA, $enrollmentA, 82.5)->assertOk(); // B, 3.0
    crSign($sheetA)->assertOk();
    $sheetB = crOpenInitial($offeringB);
    crSave($sheetB, $enrollmentB, 91)->assertOk(); // A, 4.0
    crSign($sheetB)->assertOk();

    Sanctum::actingAs($head);
    crFinalize($sheetA)->assertOk();
    crFinalize($sheetB)->assertOk();

    Sanctum::actingAs($student->user);
    crMyGrades()->assertOk()
        ->assertJsonPath('data.cgpa', 3.5) // (3.0 + 4.0) over two equal-weight 3 CH courses
        ->assertJsonPath('data.semesters.1.gpa', 3)
        ->assertJsonPath('data.semesters.1.courses.0.grade.letter_grade', 'B')
        ->assertJsonPath('data.semesters.1.courses.0.grade_history', []);

    Sanctum::actingAs($instructor);
    $correctionSheetId = crStartCorrection($sheetA, [$enrollmentA->id])->json('data.sheet.id');
    crSave($correctionSheetId, $enrollmentA, 55)->assertOk(); // F, 0.0
    crSign($correctionSheetId)->assertOk();
    crSign($correctionSheetId)->assertStatus(409);

    Sanctum::actingAs($head);
    crFinalize($correctionSheetId)->assertOk();

    Sanctum::actingAs($student->user);
    $response = crMyGrades()->assertOk();

    $response->assertJsonPath('data.cgpa', 2) // the corrected F replaces the B in the CGPA
        ->assertJsonPath('data.semesters.1.gpa', 0)
        ->assertJsonPath('data.semesters.1.courses.0.grade.letter_grade', 'F')
        ->assertJsonPath('data.semesters.1.courses.0.grade_history.0.letter_grade', 'B')
        ->assertJsonPath('data.semesters.1.courses.0.grade_history.0.superseded', true);
});

it('shows the earlier finalized grade as history on the department head review of a correction round', function () {
    $department = Department::factory()->create();
    $instructor = crInstructor();
    $head = crDeptHead($department->id);
    $semester = Semester::factory()->create(['starts_at' => now()->subMonth()]);
    $course = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3]);
    $offering = crOffering($instructor, $semester, $course);
    $student = crStudent();
    $enrollment = crEnroll($offering, $student);

    Sanctum::actingAs($instructor);
    $initialSheetId = crOpenInitial($offering);
    crSave($initialSheetId, $enrollment, 82.5)->assertOk();
    crSign($initialSheetId)->assertOk();

    Sanctum::actingAs($head);
    crFinalize($initialSheetId)->assertOk();

    Sanctum::actingAs($instructor);
    $correctionSheetId = crStartCorrection($initialSheetId, [$enrollment->id])->json('data.sheet.id');
    crSave($correctionSheetId, $enrollment, 91)->assertOk();
    crSign($correctionSheetId)->assertOk();

    Sanctum::actingAs($head);
    test()->getJson("/api/v1/grading/department-head/sheets/{$correctionSheetId}")
        ->assertOk()
        ->assertJsonPath('data.lines.0.grade.letter_grade', 'A')
        ->assertJsonPath('data.lines.0.grade_history.0.letter_grade', 'B');
});

it('audits a finalized correction with who corrected what', function () {
    $department = Department::factory()->create();
    $instructor = crInstructor();
    $head = crDeptHead($department->id);
    $semester = Semester::factory()->create(['starts_at' => now()->subMonth()]);
    $course = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3]);
    $offering = crOffering($instructor, $semester, $course);
    $student = crStudent();
    $enrollment = crEnroll($offering, $student);

    Sanctum::actingAs($instructor);
    $initialSheetId = crOpenInitial($offering);
    crSave($initialSheetId, $enrollment, 82.5)->assertOk();
    crSign($initialSheetId)->assertOk();

    Sanctum::actingAs($head);
    crFinalize($initialSheetId)->assertOk();

    Sanctum::actingAs($instructor);
    $correctionSheetId = crStartCorrection($initialSheetId, [$enrollment->id])->json('data.sheet.id');
    crSave($correctionSheetId, $enrollment, 55)->assertOk();
    crSign($correctionSheetId)->assertOk();

    Sanctum::actingAs($head);
    crFinalize($correctionSheetId)->assertOk();

    test()->assertDatabaseHas('audit_log', [
        'action' => 'grade.correction_finalized',
        'actor_user_id' => $head->id,
        'subject_type' => 'grade_sheet',
        'subject_id' => (string) $correctionSheetId,
    ]);
});

it('does not expose any student-initiated correction path', function () {
    $department = Department::factory()->create();
    $instructor = crInstructor();
    $head = crDeptHead($department->id);
    $semester = Semester::factory()->create(['starts_at' => now()->subMonth()]);
    $course = Course::factory()->create(['department_id' => $department->id, 'total_ch' => 3]);
    $offering = crOffering($instructor, $semester, $course);
    $student = crStudent();
    $enrollment = crEnroll($offering, $student);

    Sanctum::actingAs($instructor);
    $initialSheetId = crOpenInitial($offering);
    crSave($initialSheetId, $enrollment, 82.5)->assertOk();
    crSign($initialSheetId)->assertOk();

    Sanctum::actingAs($head);
    crFinalize($initialSheetId)->assertOk();

    Sanctum::actingAs($student->user);
    crStartCorrection($initialSheetId, [$enrollment->id])->assertForbidden();
});
