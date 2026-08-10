<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Ticket 05 — declaring an Incomplete (SRS §4.7.3, §4.7.3.1, §4.7.3.2): an Instructor marks a
| Sheet line Incomplete instead of entering a mark, stating a completion percentage (an
| attestation, ADR-0019, refused below the configured threshold) and a reason. The Course
| Status only actually becomes Incomplete at the Initial round's finalize — the same
| signature + Department-Head review as any other Grade (ADR-0023) — never at declaration.
| The resolution deadline is derived from the Calendar and never stored (ADR-0018).
| Written against the /api/v1 HTTP seam, Sanctum::actingAs(), real Postgres.
| Self-contained helpers (il* prefix) so a single-file run works.
*/

function ilInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

function ilDeptHead(int $departmentId): User
{
    return DepartmentHeadAssignment::factory()->create(['department_id' => $departmentId])->user;
}

/** An Offering in the given Department (so a Head can be attached), for the given Instructor. */
function ilOffering(User $instructor, int $departmentId, ?Semester $semester = null): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => ($semester ?? Semester::factory()->create())->id,
        'course_id' => Course::factory()->create(['department_id' => $departmentId, 'total_ch' => 3])->id,
        'instructor_id' => $instructor->id,
    ]);
}

function ilEnroll(CourseOffering $offering, CourseStatus $status = CourseStatus::Regular): Enrollment
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

function ilOpenSheet(CourseOffering $offering): int
{
    return test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');
}

function ilDeclareIncomplete(int $sheetId, int $enrollmentId, int $percentage, ?string $reason = 'Hospitalised before the final project.')
{
    $payload = ['completion_percentage' => $percentage];
    if ($reason !== null) {
        $payload['reason'] = $reason;
    }

    return test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollmentId}", $payload);
}

function ilMark(int $sheetId, int $enrollmentId, float $mark)
{
    return test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollmentId}", ['mark' => $mark]);
}

function ilSign(int $sheetId)
{
    return test()->postJson("/api/v1/grading/sheets/{$sheetId}/sign");
}

function ilFinalize(int $sheetId)
{
    return test()->postJson("/api/v1/grading/department-head/sheets/{$sheetId}/finalize");
}

function ilOutstanding()
{
    return test()->getJson('/api/v1/grading/incompletes');
}

/** Declares an Incomplete, signs and finalizes the Initial round — the full path to a live Incomplete Course Status. */
function ilDeclareAndFinalize(User $instructor, User $head, CourseOffering $offering, Enrollment $enrollment, int $percentage = 80): void
{
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);
    ilDeclareIncomplete($sheetId, $enrollment->id, $percentage)->assertOk();
    ilSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    ilFinalize($sheetId)->assertOk();
}

// ---------------------------------------------------------------------------
// Declaring
// ---------------------------------------------------------------------------

it('declares an incomplete on a sheet line, persisting the completion percentage and reason', function () {
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, Department::factory()->create()->id);
    $enrollment = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);

    $response = ilDeclareIncomplete($sheetId, $enrollment->id, 80, 'Hospitalised before the final project.')->assertOk();

    $response->assertJsonPath('data.enrollment_id', $enrollment->id)
        ->assertJsonPath('data.grade', null)
        ->assertJsonPath('data.incomplete.completion_percentage', 80)
        ->assertJsonPath('data.incomplete.reason', 'Hospitalised before the final project.');

    $this->assertDatabaseHas('incompletes', [
        'declared_on_grade_sheet_id' => $sheetId,
        'enrollment_id' => $enrollment->id,
        'declared_by' => $instructor->id,
        'completion_percentage' => 80,
    ]);
});

it('is visible to the department head reviewing the sheet', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, $department->id);
    $enrollment = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);
    ilDeclareIncomplete($sheetId, $enrollment->id, 80, 'Needed more time.')->assertOk();
    ilSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    $response = test()->getJson("/api/v1/grading/department-head/sheets/{$sheetId}")->assertOk();

    $response->assertJsonPath('data.lines.0.incomplete.completion_percentage', 80)
        ->assertJsonPath('data.lines.0.incomplete.reason', 'Needed more time.')
        ->assertJsonPath('data.lines.0.grade', null);
});

it('refuses a completion percentage below the configured threshold, naming the field', function () {
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, Department::factory()->create()->id);
    $enrollment = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);

    ilDeclareIncomplete($sheetId, $enrollment->id, 50)
        ->assertStatus(422)
        ->assertJsonValidationErrors('completion_percentage');

    $this->assertDatabaseMissing('incompletes', ['enrollment_id' => $enrollment->id]);
});

it('requires a reason when declaring an incomplete', function () {
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, Department::factory()->create()->id);
    $enrollment = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);

    ilDeclareIncomplete($sheetId, $enrollment->id, 80, null)
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');
});

it('lets a mark supersede an earlier incomplete declaration on the same line', function () {
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, Department::factory()->create()->id);
    $enrollment = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);
    ilDeclareIncomplete($sheetId, $enrollment->id, 80)->assertOk();

    ilMark($sheetId, $enrollment->id, 91)->assertOk()
        ->assertJsonPath('data.grade.letter_grade', 'A')
        ->assertJsonPath('data.incomplete', null);

    $this->assertDatabaseMissing('incompletes', ['declared_on_grade_sheet_id' => $sheetId, 'enrollment_id' => $enrollment->id]);
});

it('lets an incomplete declaration supersede an earlier mark on the same line', function () {
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, Department::factory()->create()->id);
    $enrollment = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);
    ilMark($sheetId, $enrollment->id, 91)->assertOk();

    ilDeclareIncomplete($sheetId, $enrollment->id, 80)->assertOk()
        ->assertJsonPath('data.grade', null);

    $this->assertDatabaseMissing('grades', ['grade_sheet_id' => $sheetId, 'enrollment_id' => $enrollment->id]);
});

it('counts a declared incomplete as a decided line, not blank, when signing', function () {
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, Department::factory()->create()->id);
    $incomplete = ilEnroll($offering);
    $graded = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);
    ilDeclareIncomplete($sheetId, $incomplete->id, 80)->assertOk();
    ilMark($sheetId, $graded->id, 91)->assertOk();

    ilSign($sheetId)->assertOk();
});

// ---------------------------------------------------------------------------
// Finalize — the Course Status change, never at declaration
// ---------------------------------------------------------------------------

it('leaves the course status untouched while the sheet is only signed, not yet finalized', function () {
    $department = Department::factory()->create();
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, $department->id);
    $enrollment = ilEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = ilOpenSheet($offering);
    ilDeclareIncomplete($sheetId, $enrollment->id, 80)->assertOk();
    ilSign($sheetId)->assertOk();

    expect($enrollment->fresh()->course_status)->toBe(CourseStatus::Regular);
});

it('becomes Incomplete via the Enrollment facade on finalize, remembering the prior status privately', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, $department->id);
    $enrollment = ilEnroll($offering, CourseStatus::Retake);

    ilDeclareAndFinalize($instructor, $head, $offering, $enrollment);

    $fresh = $enrollment->fresh();
    expect($fresh->course_status)->toBe(CourseStatus::Incomplete)
        ->and($fresh->pre_incomplete_status)->toBe(CourseStatus::Retake);
});

it('notifies the student that an incomplete was declared, with the reason and the derived deadline', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    $enrollmentSemester = Semester::factory()->create(['starts_at' => now()->subMonths(2)]);
    $nextSemester = Semester::factory()->create(['starts_at' => now()->addMonth()]);
    $offering = ilOffering($instructor, $department->id, $enrollmentSemester);
    $enrollment = ilEnroll($offering);

    ilDeclareAndFinalize($instructor, $head, $offering, $enrollment, 80);

    $studentUserId = Student::find($enrollment->student_id)->user_id;
    $this->assertDatabaseHas('notifications', ['user_id' => $studentUserId, 'type' => 'incomplete.declared']);

    $row = DB::table('notifications')->where('user_id', $studentUserId)->where('type', 'incomplete.declared')->first();
    $data = json_decode($row->data, true);
    expect($data['enrollment_id'])->toBe($enrollment->id)
        ->and($data['reason'])->not->toBeEmpty()
        ->and($data['deadline'])->toBe($nextSemester->starts_at->copy()->addDays(63)->toIso8601String());
});

it('counts toward neither the CGPA nor earned credit while Incomplete, per the counting matrix', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    $offering = ilOffering($instructor, $department->id);
    $enrollment = ilEnroll($offering);

    ilDeclareAndFinalize($instructor, $head, $offering, $enrollment);

    Sanctum::actingAs(Student::find($enrollment->student_id)->user);
    $response = test()->getJson('/api/v1/grading/my-grades')->assertOk();

    // No CGPA-counting graded enrollment exists yet, so the CGPA is still the null dash.
    $response->assertJsonPath('data.cgpa', null);
    $row = collect($response->json('data.semesters'))->flatMap(fn (array $s): array => $s['courses'])->first();
    expect($row['course_status'])->toBe('Incomplete')
        ->and($row['grade'])->toBeNull()
        ->and($row['earned'])->toBeNull();
});

// ---------------------------------------------------------------------------
// The derived deadline
// ---------------------------------------------------------------------------

it('shows the outstanding incomplete with its deadline, derived from the next semester\'s start plus 63 days', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    $enrollmentSemester = Semester::factory()->create(['starts_at' => now()->subMonths(2)]);
    $nextSemester = Semester::factory()->create(['starts_at' => now()->addMonth()]);
    $offering = ilOffering($instructor, $department->id, $enrollmentSemester);
    $enrollment = ilEnroll($offering);

    ilDeclareAndFinalize($instructor, $head, $offering, $enrollment, 80);

    Sanctum::actingAs($instructor);
    $response = ilOutstanding()->assertOk();

    $response->assertJsonPath('data.0.enrollment_id', $enrollment->id)
        ->assertJsonPath('data.0.completion_percentage', 80)
        ->assertJsonPath('data.0.deadline', $nextSemester->starts_at->copy()->addDays(63)->toIso8601String());
});

it('has no deadline when the next semester does not exist yet — declaring is never blocked for it', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    // The last Semester on the Calendar — no successor to derive a deadline from.
    $offering = ilOffering($instructor, $department->id, Semester::factory()->create(['starts_at' => now()]));
    $enrollment = ilEnroll($offering);

    ilDeclareAndFinalize($instructor, $head, $offering, $enrollment, 80);

    Sanctum::actingAs($instructor);
    ilOutstanding()->assertOk()->assertJsonPath('data.0.deadline', null);
});

it('moves the outstanding deadline when the next semester\'s start date is corrected — nothing was frozen', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    $enrollmentSemester = Semester::factory()->create(['starts_at' => now()->subMonths(2)]);
    $nextSemester = Semester::factory()->create(['starts_at' => now()->addMonth()]);
    $offering = ilOffering($instructor, $department->id, $enrollmentSemester);
    $enrollment = ilEnroll($offering);
    ilDeclareAndFinalize($instructor, $head, $offering, $enrollment, 80);

    // Student Affairs corrects the next Semester's start date — nothing was stored on the
    // Incomplete, so the very next read reflects the correction.
    $correctedStart = $nextSemester->starts_at->copy()->addDays(20);
    $nextSemester->update(['starts_at' => $correctedStart]);

    Sanctum::actingAs($instructor);
    ilOutstanding()->assertOk()->assertJsonPath('data.0.deadline', $correctedStart->copy()->addDays(63)->toIso8601String());
});

it('picks the next semester by starts_at ordering, never by (year, term)', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $instructor = ilInstructor();
    // A Fall enrollment; the true next Semester is the following Spring, even though a
    // later-dated Fall exists too — starts_at ordering, not (year, term), decides it.
    $fall = Semester::factory()->create(['starts_at' => now()->subMonths(4)]);
    $followingSpring = Semester::factory()->create(['starts_at' => now()->addMonths(2)]);
    Semester::factory()->create(['starts_at' => now()->addMonths(8)]); // a later Fall — not "next"
    $offering = ilOffering($instructor, $department->id, $fall);
    $enrollment = ilEnroll($offering);
    ilDeclareAndFinalize($instructor, $head, $offering, $enrollment, 80);

    Sanctum::actingAs($instructor);
    ilOutstanding()->assertOk()
        ->assertJsonPath('data.0.deadline', $followingSpring->starts_at->copy()->addDays(63)->toIso8601String());
});

// ---------------------------------------------------------------------------
// Scope — the outstanding list is the Instructor's own
// ---------------------------------------------------------------------------

it('excludes another instructor\'s outstanding incompletes', function () {
    $department = Department::factory()->create();
    $head = ilDeptHead($department->id);
    $mine = ilInstructor();
    $theirs = ilInstructor();
    $offering = ilOffering($theirs, $department->id);
    $enrollment = ilEnroll($offering);
    ilDeclareAndFinalize($theirs, $head, $offering, $enrollment, 80);

    Sanctum::actingAs($mine);
    ilOutstanding()->assertOk()->assertJsonPath('data', []);
});
