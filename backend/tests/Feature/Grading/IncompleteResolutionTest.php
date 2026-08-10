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
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Ticket 05 — resolving an Incomplete (SRS §4.7.3.2): resolution opens an IncompleteResolution
| Sheet round containing only that one line; entering the mark, signing and Department-Head
| finalization behave exactly as ticket 03 established (SheetSigningReviewTest), reused rather
| than reimplemented. The resolving Grade attaches to the *original* Enrollment, so it lands in
| that Enrollment's original Semester — whose GPA therefore changes retroactively — and
| resolution restores the Course Status the Enrollment held before it became Incomplete.
| Written against the /api/v1 HTTP seam, Sanctum::actingAs(), real Postgres.
| Self-contained helpers (ir* prefix) so a single-file run works.
*/

function irInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

function irStudent(): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);

    return Student::factory()->create(['user_id' => $user->id]);
}

function irDeptHead(int $departmentId): User
{
    return DepartmentHeadAssignment::factory()->create(['department_id' => $departmentId])->user;
}

function irOffering(User $instructor, int $departmentId, ?Semester $semester = null): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => ($semester ?? Semester::factory()->create())->id,
        'course_id' => Course::factory()->create(['department_id' => $departmentId, 'total_ch' => 3])->id,
        'instructor_id' => $instructor->id,
    ]);
}

function irEnroll(CourseOffering $offering, Student $student, CourseStatus $status = CourseStatus::Regular): Enrollment
{
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => 3,
    ]);
}

function irOpenSheet(CourseOffering $offering): int
{
    return test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');
}

function irMark(int $sheetId, int $enrollmentId, float $mark)
{
    return test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollmentId}", ['mark' => $mark]);
}

function irSign(int $sheetId)
{
    return test()->postJson("/api/v1/grading/sheets/{$sheetId}/sign");
}

function irFinalize(int $sheetId)
{
    return test()->postJson("/api/v1/grading/department-head/sheets/{$sheetId}/finalize");
}

function irResolutionRound(int $incompleteId)
{
    return test()->getJson("/api/v1/grading/incompletes/{$incompleteId}/resolution");
}

/**
 * Declares an Incomplete on a fresh Enrollment and finalizes the Initial round, leaving a
 * live, outstanding Incomplete — the fixture every resolution test starts from.
 *
 * @return array{0: Incomplete, 1: Enrollment}
 */
function irLiveIncomplete(User $instructor, User $head, CourseOffering $offering, Student $student, CourseStatus $priorStatus = CourseStatus::Regular): array
{
    $enrollment = irEnroll($offering, $student, $priorStatus);

    Sanctum::actingAs($instructor);
    $sheetId = irOpenSheet($offering);
    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", [
        'completion_percentage' => 80,
        'reason' => 'Needed more time on the final project.',
    ])->assertOk();
    irSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    irFinalize($sheetId)->assertOk();

    $incomplete = Incomplete::where('enrollment_id', $enrollment->id)->firstOrFail();

    return [$incomplete, $enrollment->fresh()];
}

// ---------------------------------------------------------------------------
// The outstanding list and the lazily-opened resolution round
// ---------------------------------------------------------------------------

it('lazily opens an IncompleteResolution round containing only that one line', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $offering = irOffering($instructor, $department->id);
    [$incomplete, $enrollment] = irLiveIncomplete($instructor, $head, $offering, irStudent());

    Sanctum::actingAs($instructor);
    $response = irResolutionRound($incomplete->id)->assertOk();

    $response->assertJsonPath('data.sheet.kind', GradeSheetKind::IncompleteResolution->value)
        ->assertJsonPath('data.sheet.status', 'Draft');
    expect($response->json('data.lines'))->toHaveCount(1);
    $response->assertJsonPath('data.lines.0.enrollment_id', $enrollment->id)
        ->assertJsonPath('data.lines.0.course_status', 'Incomplete')
        ->assertJsonPath('data.lines.0.incomplete.completion_percentage', 80);
});

it('returns the same round on a second open, rather than starting another', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $offering = irOffering($instructor, $department->id);
    [$incomplete] = irLiveIncomplete($instructor, $head, $offering, irStudent());

    Sanctum::actingAs($instructor);
    $first = irResolutionRound($incomplete->id)->assertOk()->json('data.sheet.id');
    $second = irResolutionRound($incomplete->id)->assertOk()->json('data.sheet.id');

    expect($second)->toBe($first);
});

it('forbids opening the resolution round for an incomplete outside the acting instructor\'s offering', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $stranger = irInstructor();
    $offering = irOffering($instructor, $department->id);
    [$incomplete] = irLiveIncomplete($instructor, $head, $offering, irStudent());

    Sanctum::actingAs($stranger);
    irResolutionRound($incomplete->id)->assertForbidden();
});

it('409s opening a resolution round for an incomplete that was already resolved', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $offering = irOffering($instructor, $department->id);
    [$incomplete] = irLiveIncomplete($instructor, $head, $offering, irStudent());

    Sanctum::actingAs($instructor);
    $sheetId = irResolutionRound($incomplete->id)->json('data.sheet.id');
    irMark($sheetId, $incomplete->fresh()->enrollment_id, 88)->assertOk();
    irSign($sheetId)->assertOk();
    Sanctum::actingAs($head);
    irFinalize($sheetId)->assertOk();

    Sanctum::actingAs($instructor);
    irResolutionRound($incomplete->id)->assertStatus(409);
});

// ---------------------------------------------------------------------------
// Resolving — entering the mark, signing, Department-Head finalization
// ---------------------------------------------------------------------------

it('resolves by entering the outstanding mark, signing and finalizing exactly like any other Grade', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $offering = irOffering($instructor, $department->id);
    [$incomplete, $enrollment] = irLiveIncomplete($instructor, $head, $offering, irStudent());

    Sanctum::actingAs($instructor);
    $sheetId = irResolutionRound($incomplete->id)->json('data.sheet.id');
    irMark($sheetId, $enrollment->id, 88)->assertOk()->assertJsonPath('data.grade.letter_grade', 'B+');
    irSign($sheetId)->assertOk()->assertJsonPath('data.sheet.status', 'Submitted');

    Sanctum::actingAs($head);
    irFinalize($sheetId)->assertOk()->assertJsonPath('data.sheet.status', 'Finalized');

    expect($incomplete->fresh()->resolved_grade_id)->not->toBeNull();
});

it('restores the pre-incomplete Course Status on resolution — a Retake comes back a Retake', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $offering = irOffering($instructor, $department->id);
    [$incomplete, $enrollment] = irLiveIncomplete($instructor, $head, $offering, irStudent(), CourseStatus::Retake);

    Sanctum::actingAs($instructor);
    $sheetId = irResolutionRound($incomplete->id)->json('data.sheet.id');
    irMark($sheetId, $enrollment->id, 91)->assertOk();
    irSign($sheetId)->assertOk();
    Sanctum::actingAs($head);
    irFinalize($sheetId)->assertOk();

    $fresh = $enrollment->fresh();
    expect($fresh->course_status)->toBe(CourseStatus::Retake)
        ->and($fresh->pre_incomplete_status)->toBeNull()
        ->and($fresh->earned)->toBeTrue();
});

it('writes earned=false through resolveIncomplete when the resolving mark falls below C+', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $offering = irOffering($instructor, $department->id);
    [$incomplete, $enrollment] = irLiveIncomplete($instructor, $head, $offering, irStudent());

    Sanctum::actingAs($instructor);
    $sheetId = irResolutionRound($incomplete->id)->json('data.sheet.id');
    irMark($sheetId, $enrollment->id, 62)->assertOk(); // D, below C+
    irSign($sheetId)->assertOk();
    Sanctum::actingAs($head);
    irFinalize($sheetId)->assertOk();

    expect($enrollment->fresh()->earned)->toBeFalse();
});

it('attaches the resolving grade to the original Enrollment, changing that Semester\'s GPA retroactively', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $originalSemester = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $offering = irOffering($instructor, $department->id, $originalSemester);
    $student = irStudent();
    [$incomplete, $enrollment] = irLiveIncomplete($instructor, $head, $offering, $student);

    Sanctum::actingAs($student->user);
    $before = test()->getJson('/api/v1/grading/my-grades')->assertOk();
    expect($before->json('data.cgpa'))->toBeNull();

    Sanctum::actingAs($instructor);
    $sheetId = irResolutionRound($incomplete->id)->json('data.sheet.id');
    irMark($sheetId, $enrollment->id, 91)->assertOk(); // A, 4.00
    irSign($sheetId)->assertOk();
    Sanctum::actingAs($head);
    irFinalize($sheetId)->assertOk();

    Sanctum::actingAs($student->user);
    $after = test()->getJson('/api/v1/grading/my-grades')->assertOk();
    $after->assertJsonPath('data.cgpa', 4);

    $semesterRow = collect($after->json('data.semesters'))->firstWhere('semester.id', $originalSemester->id);
    expect($semesterRow)->not->toBeNull()
        ->and($semesterRow['gpa'])->toBe(4)
        ->and($semesterRow['courses'][0]['enrollment_id'])->toBe($enrollment->id)
        ->and($semesterRow['courses'][0]['course_status'])->toBe('Regular');
});

it('resolves the earlier Incomplete\'s deadline off the record once the Grade is finalized', function () {
    $department = Department::factory()->create();
    $head = irDeptHead($department->id);
    $instructor = irInstructor();
    $enrollmentSemester = Semester::factory()->create(['starts_at' => now()->subMonths(2)]);
    Semester::factory()->create(['starts_at' => now()->addMonth()]); // the "next" semester
    $offering = irOffering($instructor, $department->id, $enrollmentSemester);
    $student = irStudent();
    [$incomplete, $enrollment] = irLiveIncomplete($instructor, $head, $offering, $student);

    Sanctum::actingAs($instructor);
    // Resolved — no longer outstanding, so it drops off the Instructor's outstanding list.
    $sheetId = irResolutionRound($incomplete->id)->json('data.sheet.id');
    irMark($sheetId, $enrollment->id, 91)->assertOk();
    irSign($sheetId)->assertOk();
    Sanctum::actingAs($head);
    irFinalize($sheetId)->assertOk();

    Sanctum::actingAs($instructor);
    test()->getJson('/api/v1/grading/incompletes')->assertOk()->assertJsonPath('data', []);
});
