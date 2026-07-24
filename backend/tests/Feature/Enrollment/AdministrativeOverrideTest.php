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
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Administrative override across every Phase-3 scope guard (ticket 10, ADR-0015). A System
| (R09) / Super (R10) Administrator oversees the whole enrollment surface through the
| *existing* advisor / instructor screens: they read every advisor queue, any student's
| schedule, and any offering's register without being that advisor or the assigned
| instructor (unscoped, un-audited), and may perform the scope-guarded writes — deciding a
| request on behalf, recording/correcting attendance — with each write attributed to them
| in the Audit Log. A legitimately-scoped actor (the real advisor / assigned instructor)
| stays un-audited; a non-admin out-of-scope write is still 403. Everything is asserted as
| observable behaviour through /api/v1. Helpers are local (ovr* prefix) so the frontier's
| single-file run works.
*/

/** A signed-in administrator (defaults to System Admin; Super Admin is treated identically). */
function ovrAdmin(Role $role = Role::SystemAdmin): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    return $user;
}

/** An advisor user (R03) — holds registration.approve. */
function ovrAdvisor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Advisor);

    return $user;
}

/** An instructor user (R04). */
function ovrInstructor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/** A Semester with an open registration window. */
function ovrSemester(Term $term = Term::Fall, int $year = 2026): Semester
{
    return Semester::factory()->registrationOpen()->create(['term' => $term, 'year' => $year]);
}

/** A Student in the given Program advised by the given advisor (never first-semester). */
function ovrStudentIn(Program $program, User $advisor): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);

    return Student::factory()->create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'advisor_id' => $advisor->id,
        'enrollment_term' => 'Spring 2099',
    ]);
}

/** A Program-core Course offered in the Semester and placed in the Program's curriculum. */
function ovrCore(Program $program, Semester $semester, array $meetings = [[Weekday::Monday, '09:00', '11:00']]): CourseOffering
{
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => CourseCategory::ProgramCore,
    ]);

    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id, 'course_id' => $course->id, 'capacity' => 30]);
    foreach ($meetings as [$weekday, $start, $end]) {
        OfferingMeeting::factory()->slot($weekday, $start, $end)->create(['course_offering_id' => $offering->id]);
    }

    return $offering;
}

/** A Pending Initial request for the Student with a line per offering. */
function ovrPending(Student $student, Semester $semester, array $offerings): RegistrationRequest
{
    $request = RegistrationRequest::factory()->pending()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]);
    foreach ($offerings as $offering) {
        $request->lines()->create(['course_offering_id' => $offering->id, 'mode' => 'Regular']);
    }

    return $request->load('lines');
}

/** POST an approve-every-line decision for a request. */
function ovrDecide(RegistrationRequest $request): TestResponse
{
    return test()->postJson("/api/v1/registration/requests/{$request->id}/decision", [
        'lines' => $request->lines->map(fn ($line): array => ['id' => $line->id, 'decision' => 'approved'])->all(),
    ]);
}

/** An Offering taught by the instructor, with a single weekly Monday meeting. */
function ovrOffering(User $instructor, Semester $semester): CourseOffering
{
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'instructor_id' => $instructor->id,
        'capacity' => 30,
    ]);
    OfferingMeeting::factory()->slot(Weekday::Monday, '09:00', '11:00')->create(['course_offering_id' => $offering->id]);

    return $offering;
}

/** An active Enrollment in the Offering for a fresh Student. */
function ovrEnroll(CourseOffering $offering, CourseStatus $status = CourseStatus::Regular): Enrollment
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

// ---------------------------------------------------------------------------
// Advisee-scope reads — the advisor queue, unscoped for an admin
// ---------------------------------------------------------------------------

it('shows an administrator every advisor\'s pending requests, unscoped and un-audited', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = ovrSemester();
    ovrPending(ovrStudentIn($program, ovrAdvisor()), $semester, [ovrCore($program, $semester)]);
    ovrPending(ovrStudentIn($program, ovrAdvisor()), $semester, [ovrCore($program, $semester)]);

    ovrAdmin();

    test()->getJson('/api/v1/registration/queue')->assertOk()->assertJsonCount(2, 'data');
    $this->assertDatabaseCount('audit_log', 0);
});

// ---------------------------------------------------------------------------
// Advisee-scope write — deciding a request on behalf, audited
// ---------------------------------------------------------------------------

it('lets an administrator decide a request for a student who is not their advisee, minting and auditing', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = ovrSemester();
    $student = ovrStudentIn($program, ovrAdvisor());
    $request = ovrPending($student, $semester, [
        ovrCore($program, $semester, [[Weekday::Monday, '09:00', '11:00']]),
        ovrCore($program, $semester, [[Weekday::Tuesday, '09:00', '11:00']]),
    ]);

    $admin = ovrAdmin();
    ovrDecide($request)->assertOk()->assertJsonPath('data.status', 'Approved');

    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(2);

    // The admin-authored decision is attributed to the admin in the Audit Log (ADR-0015).
    $this->assertDatabaseHas('audit_log', [
        'action' => 'registration.decided_by_admin',
        'actor_user_id' => $admin->id,
        'subject_type' => 'registration_request',
        'subject_id' => (string) $request->id,
    ]);
});

it('does not audit the real advisor deciding their own advisee\'s request', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = ovrSemester();
    $advisor = ovrAdvisor();
    $student = ovrStudentIn($program, $advisor);
    $request = ovrPending($student, $semester, [
        ovrCore($program, $semester, [[Weekday::Monday, '09:00', '11:00']]),
        ovrCore($program, $semester, [[Weekday::Tuesday, '09:00', '11:00']]),
    ]);

    Sanctum::actingAs($advisor);
    ovrDecide($request)->assertOk()->assertJsonPath('data.status', 'Approved');

    // The legitimately-scoped advisor writes nothing to the Audit Log.
    $this->assertDatabaseCount('audit_log', 0);
});

it('does not audit an administrator who is themselves the student\'s real advisor', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = ovrSemester();

    // A user who holds the admin override (R09) AND is this student's actual advisor.
    $adminAdvisor = User::factory()->create();
    $adminAdvisor->assignRole(Role::SystemAdmin);
    $student = ovrStudentIn($program, $adminAdvisor);
    $request = ovrPending($student, $semester, [
        ovrCore($program, $semester, [[Weekday::Monday, '09:00', '11:00']]),
        ovrCore($program, $semester, [[Weekday::Tuesday, '09:00', '11:00']]),
    ]);

    Sanctum::actingAs($adminAdvisor);
    ovrDecide($request)->assertOk()->assertJsonPath('data.status', 'Approved');

    // Being the student's real Advisor is the legitimately-scoped path even for an admin —
    // like the assigned instructor in attendance, they reached no scope guard, so no audit.
    $this->assertDatabaseCount('audit_log', 0);
});

it('still forbids a non-admin advisor deciding a request for another advisor\'s advisee', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = ovrSemester();
    $request = ovrPending(ovrStudentIn($program, ovrAdvisor()), $semester, [ovrCore($program, $semester)]);

    Sanctum::actingAs(ovrAdvisor()); // a different advisor
    ovrDecide($request)->assertForbidden();

    expect(Enrollment::query()->count())->toBe(0);
    $this->assertDatabaseCount('audit_log', 0);
});

// ---------------------------------------------------------------------------
// Register-for-student (advisee scope) — admin composes a routine Pending, un-audited
// ---------------------------------------------------------------------------

it('lets an administrator compose for any student, routed Pending to the advisor and un-audited', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = ovrSemester();
    $student = ovrStudentIn($program, ovrAdvisor());
    $a = ovrCore($program, $semester, [[Weekday::Monday, '09:00', '11:00']]);
    $b = ovrCore($program, $semester, [[Weekday::Tuesday, '09:00', '11:00']]);

    ovrAdmin();

    test()->postJson("/api/v1/registration/on-behalf/students/{$student->id}/requests", [
        'lines' => [
            ['offering_id' => $a->id, 'mode' => 'Regular'],
            ['offering_id' => $b->id, 'mode' => 'Regular'],
        ],
    ])->assertCreated()->assertJsonPath('data.status', 'Pending');

    // An admin composing for any student is the same legitimate action as Student Affairs
    // (story 53) — a Pending request the advisor still decides, never born Approved since the
    // admin is not the advisor — so it is not an override write and audits nothing.
    $this->assertDatabaseHas('registration_requests', ['student_id' => $student->id, 'status' => 'Pending']);
    expect(Enrollment::query()->count())->toBe(0);
    $this->assertDatabaseCount('audit_log', 0);
});

// ---------------------------------------------------------------------------
// Student-self scope — reading any student's schedule
// ---------------------------------------------------------------------------

it('lets an administrator read any student\'s schedule, unscoped and un-audited', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = Semester::factory()->running()->create(['term' => Term::Spring, 'year' => 2026]);
    $student = ovrStudentIn($program, ovrAdvisor());
    $offering = ovrCore($program, $semester);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => 3,
    ]);

    ovrAdmin();

    test()->getJson("/api/v1/registration/students/{$student->id}/schedule")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.enrollments.0.course_status', 'Regular');
    $this->assertDatabaseCount('audit_log', 0);
});

it('lets an advisor read their own advisee\'s schedule but forbids another advisor\'s advisee', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = ovrAdvisor();
    $mine = ovrStudentIn($program, $advisor);
    $theirs = ovrStudentIn($program, ovrAdvisor());

    Sanctum::actingAs($advisor);
    test()->getJson("/api/v1/registration/students/{$mine->id}/schedule")->assertOk();
    test()->getJson("/api/v1/registration/students/{$theirs->id}/schedule")->assertForbidden();
});

// ---------------------------------------------------------------------------
// Instructor-assignment scope — reading any offering's register
// ---------------------------------------------------------------------------

it('lets an administrator read an offering\'s register they do not teach, unscoped and un-audited', function () {
    $offering = ovrOffering(ovrInstructor(), ovrSemester(Term::Spring, 2026)->fresh());

    ovrAdmin();

    test()->getJson("/api/v1/offerings/{$offering->id}/register")->assertOk();
    $this->assertDatabaseCount('audit_log', 0);
});

it('shows an administrator every offering in My Offerings so they can reach any register', function () {
    $semester = ovrSemester(Term::Spring, 2026);
    $a = ovrOffering(ovrInstructor(), $semester);
    $b = ovrOffering(ovrInstructor(), $semester);

    ovrAdmin();

    $ids = collect(test()->getJson('/api/v1/offerings/mine')->assertOk()->json('data'))->pluck('id');
    expect($ids)->toContain($a->id)->toContain($b->id);
});

// ---------------------------------------------------------------------------
// Instructor-assignment writes — attendance recorded on behalf, audited
// ---------------------------------------------------------------------------

it('lets an administrator mark attendance on an offering they do not teach, and audits it', function () {
    $offering = ovrOffering(ovrInstructor(), ovrSemester(Term::Spring, 2026));
    $enrollment = ovrEnroll($offering);
    $session = ClassSession::factory()->on('2026-03-02')->create(['course_offering_id' => $offering->id]);

    $admin = ovrAdmin();

    test()->putJson("/api/v1/class-sessions/{$session->id}/attendance", [
        'marks' => [['enrollment_id' => $enrollment->id, 'present' => true]],
    ])->assertOk();

    $this->assertDatabaseHas('attendance_marks', [
        'class_session_id' => $session->id,
        'enrollment_id' => $enrollment->id,
        'present' => true,
    ]);
    $this->assertDatabaseHas('audit_log', [
        'action' => 'attendance.recorded_by_admin',
        'actor_user_id' => $admin->id,
        'subject_type' => 'class_session',
        'subject_id' => (string) $session->id,
    ]);
});

it('lets an administrator cancel a session on an offering they do not teach, and audits it', function () {
    $offering = ovrOffering(ovrInstructor(), ovrSemester(Term::Spring, 2026));
    $session = ClassSession::factory()->on('2026-03-02')->create(['course_offering_id' => $offering->id]);

    $admin = ovrAdmin();

    test()->patchJson("/api/v1/class-sessions/{$session->id}", ['cancelled' => true])
        ->assertOk()
        ->assertJsonPath('data.cancelled', true);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'attendance.session_updated_by_admin',
        'actor_user_id' => $admin->id,
        'subject_type' => 'class_session',
        'subject_id' => (string) $session->id,
    ]);
});

it('does not audit the assigned instructor marking their own offering', function () {
    $instructor = ovrInstructor();
    $offering = ovrOffering($instructor, ovrSemester(Term::Spring, 2026));
    $enrollment = ovrEnroll($offering);
    $session = ClassSession::factory()->on('2026-03-02')->create(['course_offering_id' => $offering->id]);

    Sanctum::actingAs($instructor);
    test()->putJson("/api/v1/class-sessions/{$session->id}/attendance", [
        'marks' => [['enrollment_id' => $enrollment->id, 'present' => true]],
    ])->assertOk();

    // The assigned instructor attesting their own register writes nothing to the Audit Log.
    $this->assertDatabaseCount('audit_log', 0);
});

it('still forbids a non-admin instructor marking an offering they are not assigned to', function () {
    $offering = ovrOffering(ovrInstructor(), ovrSemester(Term::Spring, 2026));
    $enrollment = ovrEnroll($offering);
    $session = ClassSession::factory()->on('2026-03-02')->create(['course_offering_id' => $offering->id]);

    Sanctum::actingAs(ovrInstructor()); // a different instructor
    test()->putJson("/api/v1/class-sessions/{$session->id}/attendance", [
        'marks' => [['enrollment_id' => $enrollment->id, 'present' => true]],
    ])->assertForbidden();

    $this->assertDatabaseCount('audit_log', 0);
});

// ---------------------------------------------------------------------------
// R09 and R10 are treated identically (ADR-0015)
// ---------------------------------------------------------------------------

it('treats a Super Administrator the same as a System Administrator', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = ovrSemester();
    ovrPending(ovrStudentIn($program, ovrAdvisor()), $semester, [ovrCore($program, $semester)]);

    ovrAdmin(Role::SuperAdmin);

    test()->getJson('/api/v1/registration/queue')->assertOk()->assertJsonCount(1, 'data');
});
