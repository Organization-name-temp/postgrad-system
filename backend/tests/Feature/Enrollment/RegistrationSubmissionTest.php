<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Enums\ResearchType;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Catalog\Public\Enums\DegreeLevel;
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
| The Student registration cart submission (ticket 05) — turning the eligible list into a
| Pending RegistrationRequest, validated as a batch (credit-hour load, composition caps,
| duplicates, clashes, capacity), plus the own-request read. Advisor approval and the
| Enrollment mint are ticket 06. Written against the /api/v1 HTTP seam (the Phase 1/2
| pattern): real Postgres, full HTTP, RBAC re-enforced by the server.
*/

/** An open Semester with a deterministic name (so the first-semester waiver is testable). */
function openSemester(Term $term = Term::Fall, int $year = 2026): Semester
{
    return Semester::factory()->registrationOpen()->create(['term' => $term, 'year' => $year]);
}

/**
 * Sign in as a fresh Student in the given Program. `enrollmentTerm` defaults to one that
 * never matches an open Semester, so the first-semester CH waiver is off unless a test
 * asks for it. Returns the Student anchor (its id is the request's `student_id`).
 */
function cartStudent(Program $program, string $enrollmentTerm = 'Spring 2099'): Student
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'program_id' => $program->id,
        'enrollment_term' => $enrollmentTerm,
    ]);
    Sanctum::actingAs($user);

    return $student;
}

/** Place a Course into a Program's Curriculum under a Category. */
function placeIn(Program $program, Course $course, CourseCategory $category): void
{
    CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => $category,
    ]);
}

/**
 * Offer a Course in a Semester with the given weekly meetings (each [Weekday, start, end]).
 *
 * @param  list<array{0: Weekday, 1: string, 2: string}>  $meetings
 */
function offerWith(Semester $semester, Course $course, ?int $capacity = 30, array $meetings = [[Weekday::Monday, '09:00', '11:00']]): CourseOffering
{
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
 * A Program-core Course offered in the Semester and placed in the Student's Program,
 * returning the Offering (the thing a cart line names).
 *
 * @param  list<array{0: Weekday, 1: string, 2: string}>  $meetings
 */
function offeredCore(
    Program $program,
    Semester $semester,
    int $totalCh = 3,
    ?int $capacity = 30,
    array $meetings = [[Weekday::Monday, '09:00', '11:00']],
): CourseOffering {
    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => $totalCh]);
    placeIn($program, $course, CourseCategory::ProgramCore);

    return offerWith($semester, $course, $capacity, $meetings);
}

/** A current-Student Enrollment in the given Offering, with an optional earned outcome. */
function submittedEnrollment(
    Student $student,
    CourseOffering $offering,
    string $classification = 'Core',
    string $status = 'Regular',
    ?bool $earned = null,
): Enrollment {
    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => $classification,
        'course_status' => $status,
        'credit_hours' => 3,
        'earned' => $earned,
    ]);
}

/** POST a cart of lines. */
function submitCart(array $lines): TestResponse
{
    return test()->postJson('/api/v1/registration/requests', ['lines' => $lines]);
}

/** One cart line for an offering. */
function cartItem(CourseOffering $offering, string $mode = 'Regular'): array
{
    return ['offering_id' => $offering->id, 'mode' => $mode];
}

// ---------------------------------------------------------------------------
// Successful submission
// ---------------------------------------------------------------------------

it('submits a valid cart as a Pending Initial request with its lines', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    $student = cartStudent($program);

    $a = offeredCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = offeredCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    $response = submitCart([cartItem($a), cartItem($b)])->assertCreated();

    $response->assertJsonPath('data.status', 'Pending')
        ->assertJsonPath('data.kind', 'Initial')
        ->assertJsonPath('data.semester_id', $semester->id)
        ->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.classification', 'Core')
        ->assertJsonPath('data.lines.0.mode', 'Regular');

    expect(RegistrationRequest::query()->count())->toBe(1);
    $this->assertDatabaseHas('registration_requests', [
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'kind' => 'Initial',
        'status' => 'Pending',
    ]);
    $this->assertDatabaseCount('registration_request_lines', 2);
    $this->assertDatabaseHas('registration_request_lines', ['course_offering_id' => $a->id, 'mode' => 'Regular']);
});

it('records a line requested as Audit', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    $a = offeredCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = offeredCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    submitCart([cartItem($a, 'Regular'), cartItem($b, 'Audit')])->assertCreated();

    $this->assertDatabaseHas('registration_request_lines', ['course_offering_id' => $b->id, 'mode' => 'Audit']);
});

// ---------------------------------------------------------------------------
// Batch validation rejections (all 422 with the offending reason)
// ---------------------------------------------------------------------------

it('rejects a cart below the credit-hour floor', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    $a = offeredCore($program, $semester, totalCh: 3);

    submitCart([cartItem($a)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['below_min_credit_hours']);

    expect(RegistrationRequest::query()->count())->toBe(0);
});

it('waives the first-semester floor only when the canonical basis says the cart completes Preparatory targets', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester(Term::Fall, 2026);
    $student = cartStudent($program, enrollmentTerm: 'Fall 2026');

    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    placeIn($program, $course, CourseCategory::Preparatory);
    $a = offerWith($semester, $course);
    DB::table('registration_policy_projections')->insert([
        'student_id' => $student->id,
        'standing' => 'NotYetAssessed',
        'registration_ceiling_ch' => null,
        'remaining_preparatory_targets' => json_encode([$course->id], JSON_THROW_ON_ERROR),
        'remaining_exact_targets' => json_encode([$course->id], JSON_THROW_ON_ERROR),
        'remaining_elective_ch' => 0,
        'total_remaining_ch' => 3,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    submitCart([cartItem($a)])->assertCreated();

    expect(RegistrationRequest::query()->count())->toBe(1);
});

it('rejects a cart above the credit-hour ceiling', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    // Five 3-CH courses on five different weekdays = 15 CH, above the 12 ceiling.
    $days = [Weekday::Sunday, Weekday::Monday, Weekday::Tuesday, Weekday::Wednesday, Weekday::Thursday];
    $lines = array_map(
        fn (Weekday $day) => cartItem(offeredCore($program, $semester, meetings: [[$day, '09:00', '11:00']])),
        $days,
    );

    submitCart($lines)
        ->assertStatus(422)
        ->assertJsonValidationErrors(['above_max_credit_hours']);
});

it('rejects a second external elective in one cart', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $other = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    // Two Master-level courses from another active program's curriculum → both External.
    $externals = collect([Weekday::Monday, Weekday::Tuesday])->map(function (Weekday $day) use ($other, $semester) {
        $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
        placeIn($other, $course, CourseCategory::ResearchElective);

        return offerWith($semester, $course, meetings: [[$day, '09:00', '11:00']]);
    });

    submitCart($externals->map(fn ($o) => cartItem($o))->all())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['external_elective_cap']);
});

it('rejects a second research course in one cart', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    // Two level-700 PBL research courses in the own curriculum → both Research Course.
    $research = collect([Weekday::Monday, Weekday::Tuesday])->map(function (Weekday $day) use ($program, $semester) {
        $course = Course::factory()->withResearchType(ResearchType::Pbl)->create(['level' => CourseLevel::Advanced, 'total_ch' => 3]);
        placeIn($program, $course, CourseCategory::ResearchElective);

        return offerWith($semester, $course, meetings: [[$day, '09:00', '11:00']]);
    });

    submitCart($research->map(fn ($o) => cartItem($o))->all())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['research_course_cap']);
});

it('rejects the same course twice, including Regular plus Audit of it', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    $a = offeredCore($program, $semester);

    submitCart([cartItem($a, 'Regular'), cartItem($a, 'Audit')])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['duplicate_course']);

    expect(RegistrationRequest::query()->count())->toBe(0);
});

it('rejects a cart whose two offerings clash in the weekly schedule', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    $a = offeredCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = offeredCore($program, $semester, meetings: [[Weekday::Monday, '10:00', '12:00']]); // overlaps a

    submitCart([cartItem($a), cartItem($b)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['time_clash']);
});

it('rejects a cart containing a full offering', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    // A full offering (0 seats) plus a seated one — 6 CH, no clash, so only capacity bites.
    $full = offeredCore($program, $semester, capacity: 0, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $seated = offeredCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    submitCart([cartItem($full), cartItem($seated)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['offering_full']);
});

it('rejects a cart line for a course that is not eligible', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    // Offered this semester but in no curriculum at all — not eligible for this student.
    $orphan = offerWith($semester, Course::factory()->create(['level' => CourseLevel::Master]));

    submitCart([cartItem($orphan)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['lines.0.offering_id']);
});

it('rejects re-registering a course whose earlier attempt is still unresolved Incomplete', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $previous = Semester::factory()->ended()->create(['term' => Term::Spring, 'year' => 2026]);
    $current = openSemester(Term::Fall, 2026);
    $student = cartStudent($program, enrollmentTerm: 'Fall 2026');

    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3, 'code' => 'DEP501']);
    placeIn($program, $course, CourseCategory::ProgramCore);
    $oldOffering = offerWith($previous, $course);
    $newOffering = offerWith($current, $course, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);
    submittedEnrollment($student, $oldOffering, status: 'Incomplete');

    submitCart([cartItem($newOffering)])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['outstanding_incomplete']);

    expect(RegistrationRequest::query()->count())->toBe(0);
});

it('lets a student register a different external elective after failing the first one', function () {
    $own = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $otherA = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $otherB = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $previous = Semester::factory()->ended()->create(['term' => Term::Spring, 'year' => 2026]);
    $current = openSemester(Term::Fall, 2026);
    $student = cartStudent($own, enrollmentTerm: 'Fall 2026');

    $failedCourse = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    placeIn($otherA, $failedCourse, CourseCategory::ResearchElective);
    $failedOffering = offerWith($previous, $failedCourse);
    submittedEnrollment($student, $failedOffering, classification: 'ExternalElective', earned: false);

    $replacement = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 6]);
    placeIn($otherB, $replacement, CourseCategory::ResearchElective);
    $replacementOffering = offerWith($current, $replacement, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    submitCart([cartItem($replacementOffering)])->assertCreated()->assertJsonPath('data.lines.0.classification', 'ExternalElective');
});

it('does not block registration just because a failed core course now carries a must-retake obligation', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $previous = Semester::factory()->ended()->create(['term' => Term::Spring, 'year' => 2026]);
    $current = openSemester(Term::Fall, 2026);
    $student = cartStudent($program, enrollmentTerm: 'Fall 2026');

    $failedCore = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3]);
    placeIn($program, $failedCore, CourseCategory::ProgramCore);
    $failedOffering = offerWith($previous, $failedCore);
    submittedEnrollment($student, $failedOffering, classification: 'Core', earned: false);

    $newCourse = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 6]);
    placeIn($program, $newCourse, CourseCategory::ProgramCore);
    $newOffering = offerWith($current, $newCourse, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    submitCart([cartItem($newOffering)])->assertCreated();
});

// ---------------------------------------------------------------------------
// Window + one-Pending-per-semester gates
// ---------------------------------------------------------------------------

it('rejects a submission when no registration window is open', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    Semester::factory()->upcoming()->create(); // exists, but registration not open
    cartStudent($program);

    submitCart([['offering_id' => 1, 'mode' => 'Regular']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registration']);
});

it('blocks a second Pending request for the same semester with a 409', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    $student = cartStudent($program);

    RegistrationRequest::factory()->pending()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]);

    $a = offeredCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = offeredCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    submitCart([cartItem($a), cartItem($b)])->assertStatus(409);

    // The pre-existing Pending is untouched — no second request created.
    expect(RegistrationRequest::query()->count())->toBe(1);
});

it('allows a fresh cart after a Rejected request while the window is open', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    $student = cartStudent($program);

    RegistrationRequest::factory()->rejected()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
    ]);

    $a = offeredCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = offeredCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);

    submitCart([cartItem($a), cartItem($b)])->assertCreated();

    // The rejected one plus the new pending one.
    expect(RegistrationRequest::query()->count())->toBe(2);
});

// ---------------------------------------------------------------------------
// Own-request read
// ---------------------------------------------------------------------------

it('shows the student their submitted request with per-line detail', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $semester = openSemester();
    cartStudent($program);

    $a = offeredCore($program, $semester, meetings: [[Weekday::Monday, '09:00', '11:00']]);
    $b = offeredCore($program, $semester, meetings: [[Weekday::Tuesday, '09:00', '11:00']]);
    submitCart([cartItem($a), cartItem($b, 'Audit')])->assertCreated();

    test()->getJson('/api/v1/registration/requests')
        ->assertOk()
        ->assertJsonPath('data.semester.id', $semester->id)
        ->assertJsonCount(1, 'data.requests')
        ->assertJsonPath('data.requests.0.status', 'Pending')
        ->assertJsonCount(2, 'data.requests.0.lines')
        ->assertJsonPath('data.requests.0.lines.0.classification', 'Core')
        ->assertJsonPath('data.requests.0.lines.1.mode', 'Audit');
});

it('returns a null semester and no requests when no window is open', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    Semester::factory()->upcoming()->create();
    cartStudent($program);

    test()->getJson('/api/v1/registration/requests')
        ->assertOk()
        ->assertJsonPath('data.semester', null)
        ->assertJsonCount(0, 'data.requests');
});

// ---------------------------------------------------------------------------
// RBAC
// ---------------------------------------------------------------------------

it('forbids submission for a user without courses.register_self', function () {
    openSemester();
    actingAsRole(Role::Instructor);

    submitCart([['offering_id' => 1, 'mode' => 'Regular']])->assertForbidden();
});

it('403s a submission when the acting user has no student record', function () {
    openSemester();
    $user = User::factory()->create();
    $user->assignRole(Role::Student); // holds the permission, but has no Student anchor
    Sanctum::actingAs($user);

    submitCart([['offering_id' => 1, 'mode' => 'Regular']])->assertForbidden();
});

it('requires authentication to submit or read requests', function () {
    submitCart([['offering_id' => 1, 'mode' => 'Regular']])->assertUnauthorized();
    test()->getJson('/api/v1/registration/requests')->assertUnauthorized();
});

it('forbids reading requests for a user without courses.register_self', function () {
    openSemester();
    actingAsRole(Role::Instructor);

    test()->getJson('/api/v1/registration/requests')->assertForbidden();
});
