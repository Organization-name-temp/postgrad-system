<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Register-for-student (ticket 09, FR §3.2 courses.register_for_student) — staff composing a
| registration cart on a Student's behalf, written against the /api/v1 HTTP seam (real
| Postgres, full HTTP, RBAC re-enforced). Two composers by design: Student Affairs → a normal
| Pending request to the Student's Advisor; the Student's own Advisor → a request born Approved
| that mints Enrollments immediately. Every validator runs against the *target* Student.
| Helpers are self-contained (ob* prefix) so a single-file run works.
*/

/** An advisor user (R03) — holds registration.approve + courses.register_for_student. */
function obAdvisor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Advisor);

    return $user;
}

/** A Student Affairs user (R05) — holds courses.register_for_student, unscoped. */
function obStudentAffairs(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::StudentAffairs);

    return $user;
}

/** A Semester with an open registration window and a deterministic name. */
function obSemester(Term $term = Term::Fall, int $year = 2026): Semester
{
    return Semester::factory()->registrationOpen()->create(['term' => $term, 'year' => $year]);
}

/** A Student in the given Program advised by the given advisor. */
function obStudentIn(Program $program, User $advisor, string $enrollmentTerm = 'Spring 2099'): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);

    return Student::factory()->create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'advisor_id' => $advisor->id,
        'enrollment_term' => $enrollmentTerm,
    ]);
}

/** A Program-core Course offered in the Semester and placed in the Program's curriculum. */
function obCore(
    Program $program,
    Semester $semester,
    int $totalCh = 3,
    array $meetings = [[Weekday::Monday, '09:00', '11:00']],
): CourseOffering {
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => $totalCh]);
    CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => CourseCategory::ProgramCore,
    ]);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'capacity' => 30,
    ]);
    foreach ($meetings as [$weekday, $start, $end]) {
        OfferingMeeting::factory()->slot($weekday, $start, $end)->create(['course_offering_id' => $offering->id]);
    }

    return $offering;
}

/** POST a compose-on-behalf cart for the target Student. */
function obCompose(Student $student, array $offerings): TestResponse
{
    $lines = collect($offerings)->map(fn (CourseOffering $offering): array => [
        'offering_id' => $offering->id,
        'mode' => 'Regular',
    ])->all();

    return test()->postJson("/api/v1/registration/on-behalf/students/{$student->id}/requests", [
        'lines' => $lines,
    ]);
}

// ---------------------------------------------------------------------------
// SA-composed → a normal Pending request routed to the Advisor
// ---------------------------------------------------------------------------

it('lands SA-composed as a Pending request routed to the student’s advisor', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $advisor = obAdvisor();
    $student = obStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $a = obCore($program, $semester, totalCh: 6);

    Sanctum::actingAs(obStudentAffairs());
    obCompose($student, [$a])
        ->assertCreated()
        ->assertJsonPath('data.status', 'Pending')
        ->assertJsonPath('data.kind', 'Initial');

    // No Enrollment minted; the request awaits the advisor.
    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(0);
    $this->assertDatabaseHas('registration_requests', [
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'Pending',
    ]);

    // The student's live advisor is notified — exactly as a self-submission.
    $this->assertDatabaseHas('notifications', [
        'user_id' => $advisor->id,
        'type' => 'registration.submitted',
    ]);
    $this->assertDatabaseMissing('notifications', ['type' => 'registration.decided']);
});

// ---------------------------------------------------------------------------
// Advisor-composed for their own advisee → born Approved, mints immediately
// ---------------------------------------------------------------------------

it('mints immediately when the advisor composes for their own advisee (born Approved)', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $advisor = obAdvisor();
    $student = obStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $a = obCore($program, $semester, totalCh: 6, meetings: [[Weekday::Monday, '09:00', '11:00']]);

    Sanctum::actingAs($advisor);
    obCompose($student, [$a])
        ->assertCreated()
        ->assertJsonPath('data.status', 'Approved')
        ->assertJsonPath('data.lines.0.decision', 'approved');

    // The Enrollment exists in the same transaction, snapshotting its facts (ADR-0017).
    $this->assertDatabaseHas('enrollments', [
        'student_id' => $student->id,
        'course_offering_id' => $a->id,
        'course_status' => 'Regular',
        'classification' => 'Core',
        'credit_hours' => 6,
    ]);
    $this->assertDatabaseHas('registration_requests', [
        'student_id' => $student->id,
        'status' => 'Approved',
        'decided_by' => $advisor->id,
    ]);

    // The student learns their schedule exists; no submit notification fires.
    $this->assertDatabaseHas('notifications', [
        'user_id' => $student->user_id,
        'type' => 'registration.decided',
    ]);
    $this->assertDatabaseMissing('notifications', ['type' => 'registration.submitted']);
});

it('forbids an advisor composing for a student who is not their advisee', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $owner = obAdvisor();
    $student = obStudentIn($program, $owner, enrollmentTerm: 'Fall 2026');
    $a = obCore($program, $semester);

    $stranger = obAdvisor();
    Sanctum::actingAs($stranger);

    obCompose($student, [$a])->assertForbidden();

    expect(RegistrationRequest::query()->count())->toBe(0);
    expect(Enrollment::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// The validators run against the target Student
// ---------------------------------------------------------------------------

it('applies the credit-hour floor to the target student', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $advisor = obAdvisor();
    // Not first semester → the 6-hour floor applies to the target student.
    $student = obStudentIn($program, $advisor, enrollmentTerm: 'Spring 2099');
    $a = obCore($program, $semester); // one 3-CH line — below the floor

    Sanctum::actingAs(obStudentAffairs());
    obCompose($student, [$a])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['below_min_credit_hours']);

    expect(RegistrationRequest::query()->count())->toBe(0);
});

it('re-validates a born-Approved cart against the advisee’s record (duplicate blocked, nothing minted)', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $advisor = obAdvisor();
    $student = obStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $dup = obCore($program, $semester);
    // Already enrolled in that exact offering this semester.
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $dup->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => 3,
    ]);

    Sanctum::actingAs($advisor);
    obCompose($student, [$dup])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['duplicate_enrollment']);

    // Nothing new minted, no request created.
    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(1);
    expect(RegistrationRequest::query()->count())->toBe(0);
});

it('409s a compose when the student already has a pending request for the semester', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $advisor = obAdvisor();
    $student = obStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');
    $a = obCore($program, $semester);

    RegistrationRequest::factory()->pending()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]);

    Sanctum::actingAs(obStudentAffairs());
    obCompose($student, [$a])->assertStatus(409);
});

// ---------------------------------------------------------------------------
// RBAC — only courses.register_for_student holders may compose
// ---------------------------------------------------------------------------

it('forbids the compose surface to a user without courses.register_for_student', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $advisor = obAdvisor();
    $student = obStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');
    $a = obCore($program, $semester);

    // A Student holds courses.register_self, never register_for_student.
    Sanctum::actingAs($student->user);
    obCompose($student, [$a])->assertForbidden();

    // An Instructor holds neither.
    actingAsRole(Role::Instructor);
    obCompose($student, [$a])->assertForbidden();
});

// ---------------------------------------------------------------------------
// The picker + the target eligible list are advisee-scoped
// ---------------------------------------------------------------------------

it('scopes the composable-students picker to advisees, and shows SA every student', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);

    $advisorA = obAdvisor();
    $advisorB = obAdvisor();
    $mine = obStudentIn($program, $advisorA);
    $theirs = obStudentIn($program, $advisorB);

    // The advisor sees only their own advisee.
    Sanctum::actingAs($advisorA);
    test()->getJson('/api/v1/registration/on-behalf/students')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student_id', $mine->id);

    // Student Affairs sees both.
    Sanctum::actingAs(obStudentAffairs());
    test()->getJson('/api/v1/registration/on-behalf/students')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('returns the target student’s eligible courses, and forbids reading a non-advisee’s list', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = obSemester();
    $advisor = obAdvisor();
    $student = obStudentIn($program, $advisor);
    $a = obCore($program, $semester);

    Sanctum::actingAs($advisor);
    test()->getJson("/api/v1/registration/on-behalf/students/{$student->id}/eligible-courses")
        ->assertOk()
        ->assertJsonPath('data.semester.id', $semester->id)
        ->assertJsonCount(1, 'data.courses')
        ->assertJsonPath('data.courses.0.offering_id', $a->id);

    // A different advisor may not read this advisee's list.
    Sanctum::actingAs(obAdvisor());
    test()->getJson("/api/v1/registration/on-behalf/students/{$student->id}/eligible-courses")
        ->assertForbidden();
});
