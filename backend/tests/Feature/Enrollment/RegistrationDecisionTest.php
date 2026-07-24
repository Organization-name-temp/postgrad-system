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
use Modules\Enrollment\Internal\Enums\RegistrationMode;
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
| The Advisor's line-item decision + atomic Enrollment mint (ticket 06) — the heart of the
| phase. Written against the /api/v1 HTTP seam (real Postgres, full HTTP, RBAC re-enforced):
| the live-scoped advisor queue, line-item approve/trim/reject, the atomic mint and its
| re-validation, the retake/duplicate rules read against the student's record, and both
| notification rows. Helpers are self-contained (dec* prefix) so a single-file run works.
*/

/** An advisor user (R03) — holds registration.approve. */
function decAdvisor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Advisor);

    return $user;
}

/** A Semester with an open registration window and a deterministic name. */
function decSemester(Term $term = Term::Fall, int $year = 2026): Semester
{
    return Semester::factory()->registrationOpen()->create(['term' => $term, 'year' => $year]);
}

/** A Student in the given Program advised by the given advisor. */
function decStudentIn(Program $program, User $advisor, string $enrollmentTerm = 'Spring 2099'): Student
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
function decCore(
    Program $program,
    Semester $semester,
    int $totalCh = 3,
    ?int $capacity = 30,
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
        'capacity' => $capacity,
    ]);
    foreach ($meetings as [$weekday, $start, $end]) {
        OfferingMeeting::factory()->slot($weekday, $start, $end)->create(['course_offering_id' => $offering->id]);
    }

    return $offering;
}

/**
 * A Pending Initial request for the Student with a line per offering.
 *
 * @param  list<CourseOffering|array{0: CourseOffering, 1: RegistrationMode}>  $offerings
 */
function decPending(Student $student, Semester $semester, array $offerings): RegistrationRequest
{
    $request = RegistrationRequest::factory()->pending()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]);

    foreach ($offerings as $entry) {
        [$offering, $mode] = is_array($entry) ? $entry : [$entry, RegistrationMode::Regular];
        $request->lines()->create(['course_offering_id' => $offering->id, 'mode' => $mode]);
    }

    return $request->load('lines');
}

/** POST a decision addressing every line — approve unless its id is in $rejectLineIds. */
function decDecide(RegistrationRequest $request, array $rejectLineIds = [], ?string $note = null): TestResponse
{
    $lines = $request->lines->map(fn ($line): array => [
        'id' => $line->id,
        'decision' => in_array($line->id, $rejectLineIds, true) ? 'rejected' : 'approved',
    ])->all();

    return test()->postJson("/api/v1/registration/requests/{$request->id}/decision", [
        'note' => $note,
        'lines' => $lines,
    ]);
}

// ---------------------------------------------------------------------------
// Advisor queue — live advisee scoping, SA unscoped
// ---------------------------------------------------------------------------

it('shows an advisor only their own advisees\' pending requests', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();

    $advisor = decAdvisor();
    $mine = decStudentIn($program, $advisor);
    decPending($mine, $semester, [decCore($program, $semester)]);

    // Another advisor's advisee — must not appear in this advisor's queue.
    $otherAdvisor = decAdvisor();
    $theirs = decStudentIn($program, $otherAdvisor);
    decPending($theirs, $semester, [decCore($program, $semester)]);

    Sanctum::actingAs($advisor);

    test()->getJson('/api/v1/registration/queue')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.student.student_id', $mine->id);
});

it('follows the student live when their advisor is reassigned', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();

    $oldAdvisor = decAdvisor();
    $newAdvisor = decAdvisor();
    $student = decStudentIn($program, $oldAdvisor);
    decPending($student, $semester, [decCore($program, $semester)]);

    // Reassign the advisee to the new advisor while the request is still Pending.
    $student->update(['advisor_id' => $newAdvisor->id]);

    Sanctum::actingAs($oldAdvisor);
    test()->getJson('/api/v1/registration/queue')->assertOk()->assertJsonCount(0, 'data');

    Sanctum::actingAs($newAdvisor);
    test()->getJson('/api/v1/registration/queue')->assertOk()->assertJsonCount(1, 'data');
});

it('shows Student Affairs the queue unscoped', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();

    $advisorA = decAdvisor();
    $advisorB = decAdvisor();
    decPending(decStudentIn($program, $advisorA), $semester, [decCore($program, $semester)]);
    decPending(decStudentIn($program, $advisorB), $semester, [decCore($program, $semester)]);

    $sa = User::factory()->create();
    $sa->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($sa);

    test()->getJson('/api/v1/registration/queue')->assertOk()->assertJsonCount(2, 'data');
});

// ---------------------------------------------------------------------------
// Line-item decision + atomic mint
// ---------------------------------------------------------------------------

it('approves every line, mints an Enrollment each, and marks the request Approved', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor);

    $a = decCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = decCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);
    $request = decPending($student, $semester, [$a, $b]);

    Sanctum::actingAs($advisor);
    decDecide($request, note: 'Looks good.')
        ->assertOk()
        ->assertJsonPath('data.status', 'Approved')
        ->assertJsonPath('data.advisor_note', 'Looks good.');

    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(2);
    $this->assertDatabaseHas('enrollments', [
        'student_id' => $student->id,
        'course_offering_id' => $a->id,
        'course_status' => 'Regular',
        'classification' => 'Core',
        'credit_hours' => 3,
    ]);
    $this->assertDatabaseHas('registration_requests', ['id' => $request->id, 'status' => 'Approved']);
});

it('trims a line — approves one, rejects one — minting only the approved Enrollment', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    // First-semester student so a single 3-CH approved line clears the waived floor.
    $student = decStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $keep = decCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $trim = decCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);
    $request = decPending($student, $semester, [$keep, $trim]);
    $trimLineId = $request->lines->firstWhere('course_offering_id', $trim->id)->id;

    Sanctum::actingAs($advisor);
    decDecide($request, rejectLineIds: [$trimLineId])->assertOk()->assertJsonPath('data.status', 'Approved');

    expect(Enrollment::query()->where('student_id', $student->id)->count())->toBe(1);
    $this->assertDatabaseHas('enrollments', ['course_offering_id' => $keep->id]);
    $this->assertDatabaseMissing('enrollments', ['course_offering_id' => $trim->id]);
});

it('rejects every line, minting nothing and marking the request Rejected', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor);

    $a = decCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = decCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);
    $request = decPending($student, $semester, [$a, $b]);

    Sanctum::actingAs($advisor);
    decDecide($request, rejectLineIds: [$request->lines[0]->id, $request->lines[1]->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'Rejected');

    expect(Enrollment::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Mint-time re-validation
// ---------------------------------------------------------------------------

it('rejects an approved subset that falls below the credit-hour floor', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor); // not first semester → floor applies

    $keep = decCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $trim = decCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);
    $request = decPending($student, $semester, [$keep, $trim]);
    $trimLineId = $request->lines->firstWhere('course_offering_id', $trim->id)->id;

    Sanctum::actingAs($advisor);
    // Approving only one 3-CH line leaves 3 CH — below the 6-hour floor.
    decDecide($request, rejectLineIds: [$trimLineId])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['below_min_credit_hours']);

    expect(Enrollment::query()->count())->toBe(0);
    $this->assertDatabaseHas('registration_requests', ['id' => $request->id, 'status' => 'Pending']);
});

it('blocks approving a course the student is already enrolled in this semester', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $dup = decCore($program, $semester);
    // Already enrolled in that exact offering.
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $dup->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => 3,
    ]);

    $request = decPending($student, $semester, [$dup]);

    Sanctum::actingAs($advisor);
    decDecide($request)->assertStatus(422)->assertJsonValidationErrors(['duplicate_enrollment']);
});

// ---------------------------------------------------------------------------
// Retake derivation against the student's record
// ---------------------------------------------------------------------------

it('derives a Retake when a prior for-credit enrollment of the course exists', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    // A prior semester's for-credit enrollment of the same course.
    $priorSemester = decSemester(Term::Spring, 2026);
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => CourseCategory::ProgramCore,
    ]);
    $priorOffering = CourseOffering::factory()->create(['semester_id' => $priorSemester->id, 'course_id' => $course->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $priorOffering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Regular,
        'credit_hours' => 3,
    ]);

    // Re-register the same course this semester (a new offering of it).
    $semester = decSemester(Term::Fall, 2026);
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id, 'course_id' => $course->id]);
    OfferingMeeting::factory()->slot(Weekday::Monday, '09:00', '11:00')->create(['course_offering_id' => $offering->id]);
    $request = decPending($student, $semester, [$offering]);

    Sanctum::actingAs($advisor);
    decDecide($request)->assertOk();

    $this->assertDatabaseHas('enrollments', [
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'course_status' => 'Retake',
    ]);
});

it('blocks a second retake of the same course', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => CourseCategory::ProgramCore,
    ]);

    // Two prior for-credit attempts (a Regular and a Retake) — the one allowed retake is used.
    foreach ([[Term::Fall, 2024, CourseStatus::Regular], [Term::Spring, 2025, CourseStatus::Retake]] as [$term, $year, $status]) {
        $sem = decSemester($term, $year);
        $off = CourseOffering::factory()->create(['semester_id' => $sem->id, 'course_id' => $course->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $off->id,
            'classification' => EnrollmentClassification::Core,
            'course_status' => $status,
            'credit_hours' => 3,
        ]);
    }

    $semester = decSemester(Term::Fall, 2026);
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id, 'course_id' => $course->id]);
    OfferingMeeting::factory()->slot(Weekday::Monday, '09:00', '11:00')->create(['course_offering_id' => $offering->id]);
    $request = decPending($student, $semester, [$offering]);

    Sanctum::actingAs($advisor);
    decDecide($request)->assertStatus(422)->assertJsonValidationErrors(['retake_limit']);
    expect(Enrollment::query()->where('course_offering_id', $offering->id)->count())->toBe(0);
});

it('does not burn the retake on a prior withdrawal — a fresh Regular', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => CourseCategory::ProgramCore,
    ]);
    $priorSemester = decSemester(Term::Spring, 2026);
    $priorOffering = CourseOffering::factory()->create(['semester_id' => $priorSemester->id, 'course_id' => $course->id]);
    Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $priorOffering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => CourseStatus::Withdrawn,
        'credit_hours' => 3,
    ]);

    $semester = decSemester(Term::Fall, 2026);
    $offering = CourseOffering::factory()->create(['semester_id' => $semester->id, 'course_id' => $course->id]);
    OfferingMeeting::factory()->slot(Weekday::Monday, '09:00', '11:00')->create(['course_offering_id' => $offering->id]);
    $request = decPending($student, $semester, [$offering]);

    Sanctum::actingAs($advisor);
    decDecide($request)->assertOk();

    $this->assertDatabaseHas('enrollments', [
        'course_offering_id' => $offering->id,
        'course_status' => 'Regular',
    ]);
});

// ---------------------------------------------------------------------------
// Scope guard + status gate
// ---------------------------------------------------------------------------

it('forbids an advisor deciding a request for a student who is not their advisee', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $owner = decAdvisor();
    $student = decStudentIn($program, $owner);
    $request = decPending($student, $semester, [decCore($program, $semester)]);

    $stranger = decAdvisor();
    Sanctum::actingAs($stranger);

    decDecide($request)->assertForbidden();
    expect(Enrollment::query()->count())->toBe(0);
});

it('409s a decision on an already-decided request', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor);
    $request = decPending($student, $semester, [
        decCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]),
        decCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]),
    ]);

    Sanctum::actingAs($advisor);
    decDecide($request)->assertOk();
    decDecide($request->fresh()->load('lines'))->assertStatus(409);
});

it('forbids the decision surface to a user without registration.approve', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor);
    $request = decPending($student, $semester, [decCore($program, $semester)]);

    actingAsRole(Role::Instructor);
    test()->getJson('/api/v1/registration/queue')->assertForbidden();
    decDecide($request)->assertForbidden();
});

// ---------------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------------

it('notifies the student when their request is decided', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor);
    $request = decPending($student, $semester, [
        decCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]),
        decCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]),
    ]);

    Sanctum::actingAs($advisor);
    decDecide($request)->assertOk();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $student->user_id,
        'type' => 'registration.decided',
    ]);
});

it('notifies the advisor when a student submits a registration', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = decSemester();
    $advisor = decAdvisor();
    $student = decStudentIn($program, $advisor, enrollmentTerm: 'Fall 2026');

    $a = decCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    Sanctum::actingAs($student->user);

    test()->postJson('/api/v1/registration/requests', [
        'lines' => [['offering_id' => $a->id, 'mode' => 'Regular']],
    ])->assertCreated();

    $this->assertDatabaseHas('notifications', [
        'user_id' => $advisor->id,
        'type' => 'registration.submitted',
    ]);
});
