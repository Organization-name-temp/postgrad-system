<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

uses(RefreshDatabase::class);

/*
| Ticket 03 — the two-party signature loop (ADR-0023): an Instructor signs a completed Grade
| Sheet as one act; the Sheet locks and routes to the right Department Head, who either
| finalizes it (writing `earned` onto every Enrollment, ADR-0022, and closing the round for
| good) or returns it with a note. Written against the /api/v1 HTTP seam, Sanctum::actingAs(),
| real Postgres — the same seam as ticket 01's GradeSheetTest.
| Self-contained helpers (sr* prefix) so a single-file run works.
*/

function srInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/** A Department Head assigned to `$departmentId` (or university-wide when true is passed). */
function srDeptHead(int|true|null $department): User
{
    $assignment = $department === true
        ? DepartmentHeadAssignment::factory()->universityWide()->create()
        : DepartmentHeadAssignment::factory()->create(['department_id' => $department]);

    return $assignment->user;
}

/** An Offering taught by the given instructor, for a Course in the given Department (null = university-wide). */
function srOffering(User $instructor, ?int $departmentId): CourseOffering
{
    $course = Course::factory()->create(['department_id' => $departmentId, 'total_ch' => 3]);

    return CourseOffering::factory()->create([
        'semester_id' => Semester::factory()->create()->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
    ]);
}

/** A fresh Student enrolled in the Offering, for-credit by default. */
function srEnroll(CourseOffering $offering, CourseStatus $status = CourseStatus::Regular): Enrollment
{
    $student = Student::factory()->create(['user_id' => User::factory()->create()->id]);

    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => 3,
    ]);
}

/** Open the Offering's sheet and grade every given enrollment with the same mark. Returns the sheet id. */
function srGradeAll(CourseOffering $offering, array $enrollments, float $mark = 80): int
{
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');
    foreach ($enrollments as $enrollment) {
        test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => $mark])->assertOk();
    }

    return $sheetId;
}

function srSign(int $sheetId)
{
    return test()->postJson("/api/v1/grading/sheets/{$sheetId}/sign");
}

function srQueue()
{
    return test()->getJson('/api/v1/grading/department-head/sheets');
}

function srShow(int $sheetId)
{
    return test()->getJson("/api/v1/grading/department-head/sheets/{$sheetId}");
}

function srFinalize(int $sheetId)
{
    return test()->postJson("/api/v1/grading/department-head/sheets/{$sheetId}/finalize");
}

function srReturn(int $sheetId, ?string $note)
{
    return test()->postJson("/api/v1/grading/department-head/sheets/{$sheetId}/return", $note === null ? [] : ['note' => $note]);
}

// ---------------------------------------------------------------------------
// Signing — the Instructor's act
// ---------------------------------------------------------------------------

it('signs a fully-graded sheet, moving Draft to Submitted', function () {
    $instructor = srInstructor();
    $offering = srOffering($instructor, Department::factory()->create()->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);

    srSign($sheetId)->assertOk()->assertJsonPath('data.sheet.status', GradeSheetStatus::Submitted->value);

    expect(GradeSheet::find($sheetId)->status)->toBe(GradeSheetStatus::Submitted);
});

it('refuses to sign a sheet with a blank line, naming how many remain', function () {
    $instructor = srInstructor();
    $offering = srOffering($instructor, Department::factory()->create()->id);
    $graded = srEnroll($offering);
    srEnroll($offering); // left blank
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$graded]);

    srSign($sheetId)->assertStatus(422)->assertJsonValidationErrors('lines');

    expect(GradeSheet::find($sheetId)->status)->toBe(GradeSheetStatus::Draft);
});

it('409s signing a sheet that is already submitted', function () {
    $instructor = srInstructor();
    $offering = srOffering($instructor, Department::factory()->create()->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    srSign($sheetId)->assertStatus(409);
});

it('forbids signing an offering the acting user does not teach', function () {
    $offering = srOffering(srInstructor(), Department::factory()->create()->id);
    srEnroll($offering);
    Sanctum::actingAs(srInstructor());

    // The sheet is opened by the real instructor to grade it, then signed by a stranger.
    $sheetId = GradeSheet::factory()->create(['course_offering_id' => $offering->id, 'kind' => GradeSheetKind::Initial])->id;

    srSign($sheetId)->assertForbidden();
});

it('the sheet is read-only to its own instructor once submitted', function () {
    $instructor = srInstructor();
    $offering = srOffering($instructor, Department::factory()->create()->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 50])
        ->assertStatus(409);
});

it('notifies every department head configured for the offering\'s department on sign', function () {
    $department = Department::factory()->create();
    $headA = srDeptHead($department->id);
    $headB = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);

    srSign($sheetId)->assertOk();

    test()->assertDatabaseHas('notifications', ['user_id' => $headA->id, 'type' => 'grade.sheet_submitted']);
    test()->assertDatabaseHas('notifications', ['user_id' => $headB->id, 'type' => 'grade.sheet_submitted']);
});

// ---------------------------------------------------------------------------
// Department-Head routing — the queue
// ---------------------------------------------------------------------------

it('shows a submitted sheet in its department head\'s queue', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    $response = srQueue()->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonPath('data.0.id', $sheetId)
        ->assertJsonPath('data.0.course_offering_id', $offering->id);
});

it('hides a submitted sheet from another department\'s head', function () {
    $mine = Department::factory()->create();
    $theirs = Department::factory()->create();
    $stranger = srDeptHead($theirs->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $mine->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($stranger);
    srQueue()->assertOk()->assertJsonPath('data', []);
    srShow($sheetId)->assertForbidden();
});

it('routes a department-less preparatory sheet to the university-wide head, and not to a department-scoped head', function () {
    $universityHead = srDeptHead(true);
    $scopedHead = srDeptHead(Department::factory()->create()->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, null); // university-wide (Preparatory) course
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($universityHead);
    srQueue()->assertOk()->assertJsonPath('data.0.id', $sheetId);
    srShow($sheetId)->assertOk();

    Sanctum::actingAs($scopedHead);
    srQueue()->assertOk()->assertJsonPath('data', []);
    srShow($sheetId)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Review — show, finalize, return
// ---------------------------------------------------------------------------

it('shows every mark and the grade distribution to the assigned department head', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment], mark: 82);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    $response = srShow($sheetId)->assertOk();

    $response->assertJsonPath('data.lines.0.enrollment_id', $enrollment->id)
        ->assertJsonPath('data.lines.0.grade.letter_grade', 'B');

    $distribution = collect($response->json('data.distribution'))->keyBy('letter');
    expect($distribution['B']['count'])->toBe(1)
        ->and($distribution['A+']['count'])->toBe(0);
});

it('a department head cannot write a mark', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);

    Sanctum::actingAs($head);
    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 90])
        ->assertForbidden();
});

it('finalizes a submitted sheet, permanently closing it', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    srFinalize($sheetId)->assertOk()->assertJsonPath('data.sheet.status', GradeSheetStatus::Finalized->value);

    srFinalize($sheetId)->assertStatus(409);
    srReturn($sheetId, 'too late')->assertStatus(409);
});

it('rolls back Grade finalization when stale credit-request invalidation cannot commit', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    $request = TransferRequest::query()->create([
        'student_id' => $enrollment->student_id,
        'curriculum_version_id' => 1,
        'target_course_id' => $offering->course_id,
        'target_course_code' => Course::query()->findOrFail($offering->course_id)->code,
        'target_category' => 'ProgramCore',
        'target_credit_hours' => 3,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-FAIL',
        'external_course_name' => 'Failed target',
        'source_credit_hours' => '3.00',
        'external_grade' => 'B',
        'document_ids' => [],
        'status' => TransferRequestStatus::PendingAdvisor,
        'revision' => 1,
        'submitted_at' => now(),
    ]);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment], mark: 50);
    srSign($sheetId)->assertOk();
    app()->instance(Notifier::class, new class implements Notifier
    {
        public function notify(int $userId, NotificationType $type, array $payload = []): void
        {
            throw new RuntimeException('Notification write failed.');
        }

        public function notifyOnce(int $userId, NotificationType $type, string $key, array $payload = []): void
        {
            throw new RuntimeException('Notification write failed.');
        }
    });
    Sanctum::actingAs($head);
    $this->withoutExceptionHandling();

    expect(fn () => srFinalize($sheetId))->toThrow(RuntimeException::class, 'Notification write failed.');

    expect(GradeSheet::query()->findOrFail($sheetId)->status)->toBe(GradeSheetStatus::Submitted)
        ->and($enrollment->fresh()->earned)->toBeNull()
        ->and($request->fresh()->status)->toBe(TransferRequestStatus::PendingAdvisor)
        ->and($request->transitions()->count())->toBe(0);
});

it('rolls back a failed Grade when a conflicting Transfer approval committed first', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    $course = Course::query()->findOrFail($offering->course_id);
    $request = TransferRequest::query()->create([
        'student_id' => $enrollment->student_id,
        'curriculum_version_id' => 1,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => 3,
        'external_institution' => 'External University',
        'external_course_code' => 'EXT-APPROVED',
        'external_course_name' => 'Approved target',
        'source_credit_hours' => '3.00',
        'external_grade' => 'A',
        'document_ids' => [],
        'normalized_grade' => 'A',
        'verified_by_user_id' => $head->id,
        'verified_at' => now(),
        'status' => TransferRequestStatus::Approved,
        'revision' => 1,
        'submitted_at' => now(),
    ]);
    $decision = TransferDecision::query()->create([
        ...$request->only([
            'student_id', 'curriculum_version_id', 'target_course_id', 'target_course_code',
            'target_category', 'target_credit_hours', 'external_institution',
            'external_course_code', 'external_course_name', 'source_credit_hours',
            'external_grade', 'document_ids', 'normalized_grade', 'verified_by_user_id',
            'verified_at',
        ]),
        'approved_by_user_id' => $head->id,
        'approved_at' => now(),
        'transfer_request_id' => $request->id,
    ]);

    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment], mark: 50);
    srSign($sheetId)->assertOk();
    Sanctum::actingAs($head);

    srFinalize($sheetId)->assertConflict()
        ->assertJsonPath('message', __('credit_decisions.grade_finalization_conflict'));

    expect(GradeSheet::query()->findOrFail($sheetId)->status)->toBe(GradeSheetStatus::Submitted)
        ->and($enrollment->fresh()->earned)->toBeNull()
        ->and($decision->fresh()->revocation)->toBeNull();
});

it('409s finalizing a sheet that was never submitted', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');

    Sanctum::actingAs($head);
    srFinalize($sheetId)->assertStatus(409);
});

it('forbids finalizing or returning a sheet outside the head\'s department', function () {
    $mine = Department::factory()->create();
    $theirs = Department::factory()->create();
    $stranger = srDeptHead($theirs->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $mine->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($stranger);
    srFinalize($sheetId)->assertForbidden();
    srReturn($sheetId, 'note')->assertForbidden();
});

it('writes earned=true for a grade at or above C+, through the enrollment facade, in the finalize transaction', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $pass = srEnroll($offering);
    $fail = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = test()->getJson("/api/v1/grading/offerings/{$offering->id}/sheet")->json('data.sheet.id');
    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$pass->id}", ['mark' => 90])->assertOk();
    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$fail->id}", ['mark' => 40])->assertOk();
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    srFinalize($sheetId)->assertOk();

    expect($pass->fresh()->earned)->toBeTrue()
        ->and($fail->fresh()->earned)->toBeFalse();
});

it('notifies each graded student on finalize, carrying the letter and whether it is below C+', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment], mark: 62);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    srFinalize($sheetId)->assertOk();

    $studentUserId = Student::find($enrollment->student_id)->user_id;
    test()->assertDatabaseHas('notifications', [
        'user_id' => $studentUserId,
        'type' => 'grade.finalized',
    ]);

    $row = DB::table('notifications')->where('user_id', $studentUserId)->where('type', 'grade.finalized')->first();
    $data = json_decode($row->data, true);
    expect($data['letter_grade'])->toBe('D')
        ->and($data['below_c_plus'])->toBeTrue();
});

it('returns a submitted sheet with a note, making it editable and re-signable', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    srReturn($sheetId, 'Please double-check line 1.')->assertOk()
        ->assertJsonPath('data.sheet.status', GradeSheetStatus::Returned->value);

    test()->assertDatabaseHas('notifications', [
        'user_id' => $instructor->id,
        'type' => 'grade.sheet_returned',
    ]);

    // Editable again, and re-signable back into the same queue.
    Sanctum::actingAs($instructor);
    test()->putJson("/api/v1/grading/sheets/{$sheetId}/lines/{$enrollment->id}", ['mark' => 91])->assertOk();
    srSign($sheetId)->assertOk()->assertJsonPath('data.sheet.status', GradeSheetStatus::Submitted->value);

    Sanctum::actingAs($head);
    srQueue()->assertOk()->assertJsonPath('data.0.id', $sheetId);
});

it('requires a note to return a sheet', function () {
    $department = Department::factory()->create();
    $head = srDeptHead($department->id);
    $instructor = srInstructor();
    $offering = srOffering($instructor, $department->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);
    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    Sanctum::actingAs($head);
    srReturn($sheetId, null)->assertStatus(422)->assertJsonValidationErrors('note');
});

it('shows the instructor\'s offerings list carrying the sheet\'s current state', function () {
    $instructor = srInstructor();
    $offering = srOffering($instructor, Department::factory()->create()->id);
    $enrollment = srEnroll($offering);
    Sanctum::actingAs($instructor);

    test()->getJson('/api/v1/grading/offerings')->assertOk()
        ->assertJsonPath('data.0.sheet_status', null);

    $sheetId = srGradeAll($offering, [$enrollment]);
    srSign($sheetId)->assertOk();

    test()->getJson('/api/v1/grading/offerings')->assertOk()
        ->assertJsonPath('data.0.sheet_status', GradeSheetStatus::Submitted->value);
});
