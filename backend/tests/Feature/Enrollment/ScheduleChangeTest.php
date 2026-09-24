<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;
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
| Period-gated schedule changes (ticket 07, FR-REG-004): free add/drop, self-serve
| withdrawal, and the add-only late-add request. Written against the /api/v1 HTTP seam
| (real Postgres, full HTTP, RBAC re-enforced). Every period is exercised by freezing time
| with travelTo against a Semester with fixed, well-ordered dates — the same action is
| accepted inside its window and refused (409) outside it. Self-contained helpers (chg*
| prefix) so a single-file run works.
*/

// The Fall 2026 window used by every case; travelTo lands the test in a chosen period.
const CHG_REGISTRATION = '2026-08-15 12:00'; // reg open, before the term starts
const CHG_ADD_DROP = '2026-09-10 12:00';     // after reg closes, inside add/drop
const CHG_LATE_ADD = '2026-09-18 12:00';     // after add/drop, inside late-add
const CHG_WITHDRAWAL = '2026-10-15 12:00';   // after late-add, inside withdrawal
const CHG_POST_DEADLINE = '2026-12-01 12:00'; // after the withdrawal deadline

/** An advisor user (R03). */
function chgAdvisor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Advisor);

    return $user;
}

/** A Fall 2026 Semester with fixed, well-ordered period dates (travelTo picks the period). */
function chgSemester(): Semester
{
    return Semester::factory()->create([
        'term' => Term::Fall,
        'year' => 2026,
        'registration_opens_at' => Carbon::parse('2026-08-01'),
        'registration_closes_at' => Carbon::parse('2026-09-05'),
        'starts_at' => Carbon::parse('2026-09-01'),
        'add_drop_ends_at' => Carbon::parse('2026-09-14'),
        'late_add_ends_at' => Carbon::parse('2026-09-21'),
        'withdrawal_deadline_at' => Carbon::parse('2026-11-15'),
        'ends_at' => Carbon::parse('2026-12-20'),
    ]);
}

/** Sign in as a fresh Student in the Program advised by the advisor. Default: not first-semester. */
function chgStudentIn(Program $program, User $advisor, string $enrollmentTerm = 'Spring 2099'): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'advisor_id' => $advisor->id,
        'enrollment_term' => $enrollmentTerm,
    ]);
    Sanctum::actingAs($user);

    return $student;
}

/** A Program-core Course offered in the Semester and placed in the Program's curriculum. */
function chgCore(
    Program $program,
    Semester $semester,
    Weekday $day = Weekday::Monday,
    int $totalCh = 3,
    ?int $capacity = 30,
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
        'capacity' => $capacity,
    ]);
    OfferingMeeting::factory()->slot($day, '09:00', '11:00')->create(['course_offering_id' => $offering->id]);

    return $offering;
}

/** An external-elective offering — a Course in another active graduate Program's curriculum only. */
function chgExternal(Program $ownProgram, Semester $semester, Weekday $day = Weekday::Wednesday): CourseOffering
{
    $other = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    CurriculumEntry::factory()->create([
        'program_id' => $other->id,
        'course_id' => $course->id,
        'category' => CourseCategory::ResearchElective,
    ]);

    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id, 'course_id' => $course->id]);
    OfferingMeeting::factory()->slot($day, '09:00', '11:00')->create(['course_offering_id' => $offering->id]);

    return $offering;
}

/** An approved Initial request — the gate free add/drop requires. */
function chgApprovedInitial(Student $student, Semester $semester): RegistrationRequest
{
    return RegistrationRequest::factory()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'kind' => RegistrationKind::Initial,
        'status' => RegistrationStatus::Approved,
    ]);
}

/** A minted Enrollment for the Student in the Offering. */
function chgEnroll(Student $student, CourseOffering $offering, int $ch = 3, CourseStatus $status = CourseStatus::Regular): Enrollment
{
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => $ch,
    ]);
}

// ---------------------------------------------------------------------------
// Free add
// ---------------------------------------------------------------------------

it('free-adds an eligible course during the add/drop period and notifies the advisor', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = chgAdvisor();
    $student = chgStudentIn($program, $advisor);
    $semester = chgSemester();

    // An approved initial + a 6-CH schedule already in place.
    chgApprovedInitial($student, $semester);
    chgEnroll($student, chgCore($program, $semester, Weekday::Monday));
    chgEnroll($student, chgCore($program, $semester, Weekday::Tuesday));

    $addable = chgCore($program, $semester, Weekday::Wednesday);

    test()->postJson('/api/v1/registration/enrollments', [
        'offering_id' => $addable->id,
        'mode' => 'Regular',
    ])->assertCreated()
        ->assertJsonPath('data.course_status', 'Regular')
        ->assertJsonPath('data.classification', 'Core');

    $this->assertDatabaseHas('enrollments', [
        'student_id' => $student->id,
        'course_offering_id' => $addable->id,
        'course_status' => 'Regular',
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $advisor->id,
        'type' => 'enrollment.adjusted',
    ]);
});

it('refuses a free add to a student with no approved initial request', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    $addable = chgCore($program, $semester);

    // No approved Initial request → the initial schedule cannot be bypassed.
    test()->postJson('/api/v1/registration/enrollments', [
        'offering_id' => $addable->id,
        'mode' => 'Regular',
    ])->assertForbidden();

    expect(Enrollment::query()->count())->toBe(0);
});

it('refuses a free add outside the add/drop window', function () {
    $this->travelTo(Carbon::parse(CHG_WITHDRAWAL)); // in the withdrawal window, not add/drop

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);
    $addable = chgCore($program, $semester);

    test()->postJson('/api/v1/registration/enrollments', [
        'offering_id' => $addable->id,
        'mode' => 'Regular',
    ])->assertStatus(409);

    expect(Enrollment::query()->count())->toBe(0);
});

it('refuses free-adding an external elective — it needs a request', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);
    $external = chgExternal($program, $semester);

    test()->postJson('/api/v1/registration/enrollments', [
        'offering_id' => $external->id,
        'mode' => 'Regular',
    ])->assertStatus(422)->assertJsonValidationErrors(['offering_id']);

    expect(Enrollment::query()->where('course_offering_id', $external->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Free drop
// ---------------------------------------------------------------------------

it('free-drops a course during the add/drop period, hard-deleting it and notifying the advisor', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = chgAdvisor();
    $student = chgStudentIn($program, $advisor);
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);

    // 9 CH so dropping one still clears the 6-CH floor.
    $drop = chgEnroll($student, chgCore($program, $semester, Weekday::Monday));
    chgEnroll($student, chgCore($program, $semester, Weekday::Tuesday));
    chgEnroll($student, chgCore($program, $semester, Weekday::Wednesday));

    test()->deleteJson("/api/v1/registration/enrollments/{$drop->id}")->assertNoContent();

    $this->assertDatabaseMissing('enrollments', ['id' => $drop->id]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $advisor->id,
        'type' => 'enrollment.adjusted',
    ]);
});

it('blocks a free drop that would take the load below the credit-hour floor', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor()); // not first semester → floor applies
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);

    // Exactly 6 CH — dropping one leaves 3, below the floor.
    $drop = chgEnroll($student, chgCore($program, $semester, Weekday::Monday));
    chgEnroll($student, chgCore($program, $semester, Weekday::Tuesday));

    test()->deleteJson("/api/v1/registration/enrollments/{$drop->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['below_min_credit_hours']);

    $this->assertDatabaseHas('enrollments', ['id' => $drop->id]);
});

it('does not grant a blanket below-floor waiver merely because it is the student\'s first semester', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    // There is no remaining Preparatory completion set, so the normal floor applies.
    $student = chgStudentIn($program, chgAdvisor(), enrollmentTerm: 'Fall 2026');
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);

    $drop = chgEnroll($student, chgCore($program, $semester, Weekday::Monday));
    chgEnroll($student, chgCore($program, $semester, Weekday::Tuesday));

    test()->deleteJson("/api/v1/registration/enrollments/{$drop->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors(['below_min_credit_hours']);
    $this->assertDatabaseHas('enrollments', ['id' => $drop->id]);
});

it('refuses a free drop outside the add/drop window', function () {
    $this->travelTo(Carbon::parse(CHG_WITHDRAWAL));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);
    $enrollment = chgEnroll($student, chgCore($program, $semester));

    test()->deleteJson("/api/v1/registration/enrollments/{$enrollment->id}")->assertStatus(409);
    $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
});

it('refuses a free drop to a student with no approved initial request', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();

    // An enrollment exists but there is no approved Initial request — the initial schedule
    // cannot be bypassed, so even a drop is forbidden.
    $enrollment = chgEnroll($student, chgCore($program, $semester));

    test()->deleteJson("/api/v1/registration/enrollments/{$enrollment->id}")->assertForbidden();
    $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id]);
});

it('404s dropping another student\'s enrollment', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = chgAdvisor();
    $semester = chgSemester();

    $other = chgStudentIn($program, $advisor);
    chgApprovedInitial($other, $semester);
    $theirs = chgEnroll($other, chgCore($program, $semester));

    // A different acting student cannot drop it.
    chgStudentIn($program, $advisor);
    test()->deleteJson("/api/v1/registration/enrollments/{$theirs->id}")->assertNotFound();
    $this->assertDatabaseHas('enrollments', ['id' => $theirs->id]);
});

// ---------------------------------------------------------------------------
// Withdrawal
// ---------------------------------------------------------------------------

it('withdraws from a course during the withdrawal period — status W, no floor check, advisor notified', function () {
    $this->travelTo(Carbon::parse(CHG_WITHDRAWAL));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = chgAdvisor();
    $student = chgStudentIn($program, $advisor); // not first semester

    $semester = chgSemester();
    // Only 6 CH — withdrawing takes the load below the floor, which withdrawal ignores.
    $withdraw = chgEnroll($student, chgCore($program, $semester, Weekday::Monday));
    chgEnroll($student, chgCore($program, $semester, Weekday::Tuesday));

    test()->postJson("/api/v1/registration/enrollments/{$withdraw->id}/withdrawal")
        ->assertOk()
        ->assertJsonPath('data.course_status', 'Withdrawn');

    $this->assertDatabaseHas('enrollments', [
        'id' => $withdraw->id,
        'course_status' => 'Withdrawn',
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $advisor->id,
        'type' => 'enrollment.withdrawn',
    ]);
});

it('refuses a withdrawal outside the withdrawal window', function () {
    $this->travelTo(Carbon::parse(CHG_ADD_DROP)); // add/drop, not withdrawal

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    $enrollment = chgEnroll($student, chgCore($program, $semester));

    test()->postJson("/api/v1/registration/enrollments/{$enrollment->id}/withdrawal")->assertStatus(409);
    $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'course_status' => 'Regular']);
});

it('409s a second withdrawal of an already-withdrawn course', function () {
    $this->travelTo(Carbon::parse(CHG_WITHDRAWAL));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    $enrollment = chgEnroll($student, chgCore($program, $semester), status: CourseStatus::Withdrawn);

    test()->postJson("/api/v1/registration/enrollments/{$enrollment->id}/withdrawal")->assertStatus(409);
});

// ---------------------------------------------------------------------------
// Late-add — a RegistrationRequest of kind LateAdd through the advisor flow
// ---------------------------------------------------------------------------

it('submits a late-add request during the late-add window and notifies the advisor', function () {
    $this->travelTo(Carbon::parse(CHG_LATE_ADD));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = chgAdvisor();
    $student = chgStudentIn($program, $advisor);
    $semester = chgSemester();

    // An approved initial + a 6-CH schedule; the late-add adds one more course.
    chgApprovedInitial($student, $semester);
    chgEnroll($student, chgCore($program, $semester, Weekday::Monday));
    chgEnroll($student, chgCore($program, $semester, Weekday::Tuesday));

    $addable = chgCore($program, $semester, Weekday::Wednesday);

    test()->postJson('/api/v1/registration/requests', [
        'lines' => [['offering_id' => $addable->id, 'mode' => 'Regular']],
    ])->assertCreated()->assertJsonPath('data.kind', 'LateAdd');

    $this->assertDatabaseHas('registration_requests', [
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'kind' => 'LateAdd',
        'status' => 'Pending',
    ]);
    $this->assertDatabaseHas('notifications', [
        'user_id' => $advisor->id,
        'type' => 'registration.submitted',
    ]);
});

it('refuses a late-add-shaped submission outside the late-add window', function () {
    // In the withdrawal window: registration is closed and late-add has ended, so a cart
    // submission has no open target and is rejected.
    $this->travelTo(Carbon::parse(CHG_WITHDRAWAL));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);
    $addable = chgCore($program, $semester, Weekday::Wednesday);

    test()->postJson('/api/v1/registration/requests', [
        'lines' => [['offering_id' => $addable->id, 'mode' => 'Regular']],
    ])->assertStatus(422)->assertJsonValidationErrors(['registration']);

    $this->assertDatabaseMissing('registration_requests', [
        'student_id' => $student->id,
        'kind' => 'LateAdd',
    ]);
});

it('offers a late-add list that excludes courses already on the schedule', function () {
    $this->travelTo(Carbon::parse(CHG_LATE_ADD));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();

    $enrolledOffering = chgCore($program, $semester, Weekday::Monday);
    chgApprovedInitial($student, $semester);
    chgEnroll($student, $enrolledOffering);

    $addable = chgCore($program, $semester, Weekday::Tuesday);

    $response = test()->getJson('/api/v1/registration/eligible-courses')->assertOk();
    $offeringIds = collect($response->json('data.courses'))->pluck('offering_id');

    expect($offeringIds)->toContain($addable->id)
        ->and($offeringIds)->not->toContain($enrolledOffering->id);
});

// ---------------------------------------------------------------------------
// Post-deadline lockout
// ---------------------------------------------------------------------------

it('locks out every student-initiated change after the withdrawal deadline', function () {
    $this->travelTo(Carbon::parse(CHG_POST_DEADLINE));

    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $student = chgStudentIn($program, chgAdvisor());
    $semester = chgSemester();
    chgApprovedInitial($student, $semester);

    $enrollment = chgEnroll($student, chgCore($program, $semester, Weekday::Monday));
    $addable = chgCore($program, $semester, Weekday::Tuesday);

    // Add, drop and withdraw are all refused once the calendar has run out.
    test()->postJson('/api/v1/registration/enrollments', [
        'offering_id' => $addable->id,
        'mode' => 'Regular',
    ])->assertStatus(409);

    test()->deleteJson("/api/v1/registration/enrollments/{$enrollment->id}")->assertStatus(409);

    test()->postJson("/api/v1/registration/enrollments/{$enrollment->id}/withdrawal")->assertStatus(409);

    $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'course_status' => 'Regular']);
});
