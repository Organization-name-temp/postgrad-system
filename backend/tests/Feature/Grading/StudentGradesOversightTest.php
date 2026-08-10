<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Scoped grade oversight (phase 4, ticket 08) — the reviewer-facing read of a Student's
| academic record. The endpoint is driven through /api/v1 and the assertions are about scope:
| who may read which Student, that the admin override is un-audited on reads, and that the
| payload itself is the same record the Student sees on My Grades rather than a second
| implementation of the GPA/read-model logic.
| Self-contained helpers (go* prefix) so a single-file run stays legible.
*/

function goUser(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function goStudentIn(Program $program, User $advisor): Student
{
    $owner = goUser(Role::Student);

    return Student::factory()->create([
        'user_id' => $owner->id,
        'program_id' => $program->id,
        'advisor_id' => $advisor->id,
    ]);
}

function goOffering(Program $program, User $instructor, ?Semester $semester = null, ?Course $course = null): CourseOffering
{
    return CourseOffering::factory()->create([
        'semester_id' => ($semester ?? Semester::factory()->create())->id,
        'course_id' => ($course ?? Course::factory()->create([
            'department_id' => $program->department_id,
            'lecture_ch' => 3,
            'tutorial_ch' => 0,
            'lab_ch' => 0,
            'total_ch' => 3,
        ]))->id,
        'instructor_id' => $instructor->id,
    ]);
}

function goEnroll(
    CourseOffering $offering,
    Student $student,
    CourseStatus $status = CourseStatus::Regular,
    EnrollmentClassification $classification = EnrollmentClassification::Core,
    int $creditHours = 3,
): Enrollment {
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => $classification,
        'course_status' => $status,
        'credit_hours' => $creditHours,
    ]);
}

function goGrade(Enrollment $enrollment, float $mark, GradeSheetStatus $status = GradeSheetStatus::Finalized): Grade
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
        Enrollment::query()
            ->whereKey($enrollment->id)
            ->update(['earned' => (float) $grade->gpa_points >= 2.5]);
    }

    return $grade;
}

function goStudentGrades(Student $student)
{
    return test()->getJson("/api/v1/grading/students/{$student->id}");
}

function goStudentsInScope()
{
    return test()->getJson('/api/v1/grading/students');
}

function goMyGrades()
{
    return test()->getJson('/api/v1/grading/my-grades');
}

it('scopes an advisor to their own advisees and 403s another advisor', function () {
    $program = Program::factory()->create();
    $advisor = goUser(Role::Advisor);
    $mine = goStudentIn($program, $advisor);
    $theirs = goStudentIn($program, goUser(Role::Advisor));

    Sanctum::actingAs($advisor);

    goStudentGrades($mine)->assertOk();
    goStudentGrades($theirs)->assertForbidden();
});

it('scopes a department head to students in their own department programs', function () {
    $mine = Program::factory()->create();
    $theirs = Program::factory()->create();
    $head = goUser(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create([
        'user_id' => $head->id,
        'department_id' => $mine->department_id,
    ]);

    $mineStudent = goStudentIn($mine, goUser(Role::Advisor));
    $theirStudent = goStudentIn($theirs, goUser(Role::Advisor));

    Sanctum::actingAs($head);

    goStudentGrades($mineStudent)->assertOk();
    goStudentGrades($theirStudent)->assertForbidden();
});

it('scopes a dean to students in their own faculty programs', function () {
    $faculty = Faculty::factory()->create();
    $mine = Program::factory()->create(['department_id' => Department::factory()->create(['faculty_id' => $faculty->id])->id]);
    $theirs = Program::factory()->create();
    $dean = goUser(Role::Dean);
    FacultyDeanAssignment::factory()->create([
        'user_id' => $dean->id,
        'faculty_id' => $faculty->id,
    ]);

    $mineStudent = goStudentIn($mine, goUser(Role::Advisor));
    $theirStudent = goStudentIn($theirs, goUser(Role::Advisor));

    Sanctum::actingAs($dean);

    goStudentGrades($mineStudent)->assertOk();
    goStudentGrades($theirStudent)->assertForbidden();
});

it('scopes an instructor to students on their own teaching roster', function () {
    $program = Program::factory()->create();
    $instructor = goUser(Role::Instructor);
    $semester = Semester::factory()->create();

    $mine = goStudentIn($program, goUser(Role::Advisor));
    $theirs = goStudentIn($program, goUser(Role::Advisor));

    goEnroll(goOffering($program, $instructor, $semester), $mine);
    goEnroll(goOffering($program, goUser(Role::Instructor), $semester), $theirs);

    Sanctum::actingAs($instructor);

    goStudentGrades($mine)->assertOk();
    goStudentGrades($theirs)->assertForbidden();
});

it('lets Student Affairs and Council read any student', function () {
    $program = Program::factory()->create();
    $student = goStudentIn($program, goUser(Role::Advisor));

    Sanctum::actingAs(goUser(Role::StudentAffairs));
    goStudentGrades($student)->assertOk();

    Sanctum::actingAs(goUser(Role::Council));
    goStudentGrades($student)->assertOk();
});

it('forbids a student from reaching the oversight endpoint, even for themselves', function () {
    $program = Program::factory()->create();
    $advisor = goUser(Role::Advisor);
    $student = goStudentIn($program, $advisor);

    Sanctum::actingAs($student->user);

    goStudentGrades($student)->assertForbidden();
});

it('lets an administrator read any student unscoped and without auditing the read', function () {
    $program = Program::factory()->create();
    $student = goStudentIn($program, goUser(Role::Advisor));
    $admin = goUser(Role::SystemAdmin);

    Sanctum::actingAs($admin);

    goStudentGrades($student)->assertOk();
    $this->assertDatabaseCount('audit_log', 0);
});

it('returns the same academic-record payload the student sees on My Grades', function () {
    $program = Program::factory()->create();
    $advisor = goUser(Role::Advisor);
    $instructor = goUser(Role::Instructor);
    $student = goStudentIn($program, $advisor);

    $earlier = Semester::factory()->create(['starts_at' => now()->subYear()]);
    $later = Semester::factory()->create(['starts_at' => now()]);
    Semester::factory()->create(['starts_at' => now()->addYear()]);
    $repeatCourse = Course::factory()->create([
        'department_id' => $program->department_id,
        'lecture_ch' => 3,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 3,
    ]);

    $firstAttempt = goEnroll(goOffering($program, $instructor, $earlier, $repeatCourse), $student);
    goGrade($firstAttempt, 95);

    $retake = goEnroll(
        goOffering($program, $instructor, $later, $repeatCourse),
        $student,
        CourseStatus::Retake,
    );
    goGrade($retake, 62);

    $incomplete = goEnroll(
        goOffering($program, $instructor, $later),
        $student,
        CourseStatus::Incomplete,
    );

    Sanctum::actingAs($student->user);
    $mine = goMyGrades()->assertOk()->json('data');

    Sanctum::actingAs($advisor);
    $oversight = goStudentGrades($student)->assertOk()->json('data');

    expect($oversight)->toBe($mine);

    $rows = collect($oversight['semesters'])->flatMap(fn (array $semester): array => $semester['courses'])->keyBy('enrollment_id');
    expect($rows[$retake->id]['must_retake'] ?? null)->toBeTrue()
        ->and($rows[$firstAttempt->id]['superseded'] ?? null)->toBeTrue()
        ->and($rows[$incomplete->id]['incomplete_deadline'] ?? null)->not->toBeNull();
});

it('scopes the student picker to the reviewer role rather than returning every student to everyone', function () {
    $mine = Program::factory()->create();
    $theirs = Program::factory()->create();
    $head = goUser(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create([
        'user_id' => $head->id,
        'department_id' => $mine->department_id,
    ]);

    $visible = goStudentIn($mine, goUser(Role::Advisor));
    $hidden = goStudentIn($theirs, goUser(Role::Advisor));

    Sanctum::actingAs($head);
    $ids = collect(goStudentsInScope()->assertOk()->json('data'))->pluck('student_id');

    expect($ids)->toContain($visible->id)->not->toContain($hidden->id);
});
