<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Enums\ResearchType;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\CourseLevel;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The Student eligible-course list (ticket 04) — the read-only view of what a Student
| may register for in the currently-open Semester, each line placed into its Enrollment
| Classification by the pure classifier. The list is the Student's own Curriculum ∩ the
| semester's Offerings, plus other active graduate Programs' curriculum courses offered
| this semester as External Electives (ADR-0017, BR-14…26). Gated on
| courses.register_self (R02). Written against the /api/v1 HTTP seam.
*/

/** An active graduate Program at the given degree level. */
function program(DegreeLevel $degree = DegreeLevel::MSc): Program
{
    return Program::factory()->create(['degree_level' => $degree]);
}

/**
 * Sign in as a fresh Student owning a Student anchor in the given Program. The anchor is
 * what {@see StudentDirectory} resolves to find the
 * acting Student's Program and degree.
 */
function actingAsStudentIn(Program $program): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    Student::factory()->create(['user_id' => $user->id, 'program_id' => $program->id]);
    Sanctum::actingAs($user);

    return $user;
}

/** Place a Course into a Program's Curriculum under a Category. */
function place(Program $program, Course $course, CourseCategory $category): CurriculumEntry
{
    return CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => $category,
    ]);
}

/** Offer a Course in a Semester (with one meeting so the resource renders a schedule). */
function offer(Semester $semester, Course $course, ?int $capacity = 30): CourseOffering
{
    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'capacity' => $capacity,
    ]);
    OfferingMeeting::factory()->slot(Weekday::Monday, '09:00', '11:00')->create([
        'course_offering_id' => $offering->id,
    ]);

    return $offering;
}

/** GET the acting Student's eligible list. */
function eligible(): TestResponse
{
    return test()->getJson('/api/v1/registration/eligible-courses');
}

/** The classification of the eligible line for a given course id, or null if absent. */
function classificationOf(TestResponse $response, int $courseId): ?string
{
    $line = collect($response->json('data.courses'))
        ->firstWhere('course.id', $courseId);

    return $line['classification'] ?? null;
}

it('groups own-curriculum offered courses by their enrollment classification', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $program = program(DegreeLevel::MSc);

    $prep = Course::factory()->create(['level' => CourseLevel::Foundation]);
    $core = Course::factory()->create(['level' => CourseLevel::Master]);
    $internal = Course::factory()->create(['level' => CourseLevel::Master]);
    $research = Course::factory()->withResearchType(ResearchType::Pbl)->create(['level' => CourseLevel::Advanced]);

    place($program, $prep, CourseCategory::Preparatory);
    place($program, $core, CourseCategory::ProgramCore);
    place($program, $internal, CourseCategory::ResearchElective);
    place($program, $research, CourseCategory::ResearchElective);

    foreach ([$prep, $core, $internal, $research] as $course) {
        offer($semester, $course);
    }

    actingAsStudentIn($program);
    $response = eligible()->assertOk()->assertJsonPath('data.semester.id', $semester->id);

    expect(classificationOf($response, $prep->id))->toBe('Preparatory')
        ->and(classificationOf($response, $core->id))->toBe('Core')
        ->and(classificationOf($response, $internal->id))->toBe('InternalElective')
        ->and(classificationOf($response, $research->id))->toBe('ResearchCourse');

    $response->assertJsonCount(4, 'data.courses');
});

it('includes a course from another active graduate program as an external elective', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $ownProgram = program(DegreeLevel::MSc);
    $otherProgram = program(DegreeLevel::MSc);

    $external = Course::factory()->create(['level' => CourseLevel::Master]);
    place($otherProgram, $external, CourseCategory::ResearchElective); // in another program only
    offer($semester, $external);

    actingAsStudentIn($ownProgram);

    expect(classificationOf(eligible()->assertOk(), $external->id))->toBe('ExternalElective');
});

it('does not treat a course from a deactivated program as an external elective', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $ownProgram = program(DegreeLevel::MSc);
    $deadProgram = Program::factory()->create(['degree_level' => DegreeLevel::MSc, 'is_active' => false]);

    $course = Course::factory()->create(['level' => CourseLevel::Master]);
    place($deadProgram, $course, CourseCategory::ResearchElective);
    offer($semester, $course);

    actingAsStudentIn($ownProgram);

    eligible()->assertOk()->assertJsonCount(0, 'data.courses');
});

it('omits curriculum courses that have no offering this semester', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $program = program(DegreeLevel::MSc);

    $offered = Course::factory()->create(['level' => CourseLevel::Master]);
    $notOffered = Course::factory()->create(['level' => CourseLevel::Master]);
    place($program, $offered, CourseCategory::ProgramCore);
    place($program, $notOffered, CourseCategory::ProgramCore);
    offer($semester, $offered);

    actingAsStudentIn($program);
    $response = eligible()->assertOk()->assertJsonCount(1, 'data.courses');

    expect(classificationOf($response, $offered->id))->toBe('Core')
        ->and(classificationOf($response, $notOffered->id))->toBeNull();
});

it('shows only the degree-matched level-700 research option', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $program = program(DegreeLevel::MSc); // MSc → PBL matches, Seminar does not

    $pbl = Course::factory()->withResearchType(ResearchType::Pbl)->create(['level' => CourseLevel::Advanced]);
    $seminar = Course::factory()->withResearchType(ResearchType::Seminar)->create(['level' => CourseLevel::Advanced]);
    place($program, $pbl, CourseCategory::ResearchElective);
    place($program, $seminar, CourseCategory::ResearchElective);
    offer($semester, $pbl);
    offer($semester, $seminar);

    actingAsStudentIn($program);
    $response = eligible()->assertOk()->assertJsonCount(1, 'data.courses');

    expect(classificationOf($response, $pbl->id))->toBe('ResearchCourse')
        ->and(classificationOf($response, $seminar->id))->toBeNull();
});

it('excludes an offered course that is in neither the own nor another curriculum', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $program = program(DegreeLevel::MSc);

    // Offered this semester but placed in no curriculum at all — not eligible for anyone.
    $orphan = Course::factory()->create(['level' => CourseLevel::Master]);
    offer($semester, $orphan);

    actingAsStudentIn($program);

    eligible()->assertOk()->assertJsonCount(0, 'data.courses');
});

it('carries the offering instructor, meeting times, total CH and seats remaining on each line', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $program = program(DegreeLevel::MSc);

    $course = Course::factory()->create(['level' => CourseLevel::Master, 'total_ch' => 3, 'code' => 'DEP510']);
    place($program, $course, CourseCategory::ProgramCore);
    offer($semester, $course, capacity: 25);

    actingAsStudentIn($program);

    eligible()->assertOk()
        ->assertJsonPath('data.courses.0.course.code', 'DEP510')
        ->assertJsonPath('data.courses.0.total_ch', 3)
        ->assertJsonPath('data.courses.0.capacity', 25)
        ->assertJsonPath('data.courses.0.seats_remaining', 25)
        ->assertJsonPath('data.courses.0.meetings.0.starts_at', '09:00')
        ->assertJsonStructure(['data' => ['courses' => [['offering_id', 'classification', 'instructor' => ['id', 'name'], 'meetings']]]]);
});

it('reports null seats remaining for an uncapped offering', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $program = program(DegreeLevel::MSc);

    $course = Course::factory()->create(['level' => CourseLevel::Master]);
    place($program, $course, CourseCategory::ProgramCore);
    offer($semester, $course, capacity: null);

    actingAsStudentIn($program);

    eligible()->assertOk()->assertJsonPath('data.courses.0.seats_remaining', null);
});

it('returns a null semester and an empty list when no registration window is open', function () {
    Semester::factory()->upcoming()->create();
    $program = program(DegreeLevel::MSc);
    $course = Course::factory()->create(['level' => CourseLevel::Master]);
    place($program, $course, CourseCategory::ProgramCore);

    actingAsStudentIn($program);

    eligible()->assertOk()
        ->assertJsonPath('data.semester', null)
        ->assertJsonCount(0, 'data.courses');
});

it('only lists offerings from the open semester, not another semester', function () {
    $open = Semester::factory()->registrationOpen()->create();
    $other = Semester::factory()->running()->create();
    $program = program(DegreeLevel::MSc);

    $here = Course::factory()->create(['level' => CourseLevel::Master]);
    $elsewhere = Course::factory()->create(['level' => CourseLevel::Master]);
    place($program, $here, CourseCategory::ProgramCore);
    place($program, $elsewhere, CourseCategory::ProgramCore);
    offer($open, $here);
    offer($other, $elsewhere);

    actingAsStudentIn($program);
    $response = eligible()->assertOk()->assertJsonCount(1, 'data.courses');

    expect(classificationOf($response, $here->id))->toBe('Core');
});

it('classifies a PhD student\'s electives at level 600', function () {
    $semester = Semester::factory()->registrationOpen()->create();
    $program = program(DegreeLevel::PhD);

    $internal = Course::factory()->create(['level' => CourseLevel::Doctoral]);
    $research = Course::factory()->withResearchType(ResearchType::Seminar)->create(['level' => CourseLevel::Advanced]);
    place($program, $internal, CourseCategory::ResearchElective);
    place($program, $research, CourseCategory::ResearchElective);
    offer($semester, $internal);
    offer($semester, $research);

    actingAsStudentIn($program);
    $response = eligible()->assertOk();

    expect(classificationOf($response, $internal->id))->toBe('InternalElective')
        ->and(classificationOf($response, $research->id))->toBe('ResearchCourse');
});

it('403s when the acting user has no student record', function () {
    Semester::factory()->registrationOpen()->create();
    // A Student-role user with no Student anchor (an inconsistent state) cannot register.
    $user = User::factory()->create();
    $user->assignRole(Role::Student);
    Sanctum::actingAs($user);

    eligible()->assertForbidden();
});

it('forbids a user without courses.register_self', function () {
    Semester::factory()->registrationOpen()->create();
    actingAsRole(Role::Instructor); // holds no courses.register_self

    eligible()->assertForbidden();
});

it('requires authentication', function () {
    eligible()->assertUnauthorized();
});
