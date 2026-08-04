<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Grading\Internal\Support\IncompleteDeadlineResolver;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Identity\Public\Contracts\StudentDirectory;

// The seeder promises a *shape*, not just rows: the overdue Incomplete has to really be overdue
// and the superseded Retake really superseded, or the headline smoke and the whole Phase-4 demo
// quietly weaken while every other test stays green (spec §Seeder, ticket 10). These assertions
// are that alarm — they exercise the seeded world through the same facades the app reads it
// through, never by re-deriving the arithmetic here.
//
// The full DatabaseSeeder is run because GradingSeeder deliberately builds *on top of* the
// Catalog, Admissions and Enrollment worlds rather than duplicating them; the tests below are
// therefore few and dense rather than one assertion apiece.

uses(RefreshDatabase::class);

/** The Student anchor behind one seeded account, by its reserved seed email. */
function gsdStudentId(string $email): int
{
    $user = User::query()->where('email', $email)->firstOrFail();

    return app(StudentDirectory::class)->find((int) $user->getKey())->studentId;
}

/**
 * One seeded Offering, by its Semester's natural key and Course code. The Course reference is
 * an opaque id with no FK (ADR-0012), so there is no relation to join through — the Catalog is
 * queried for the id first, exactly as a consumer resolves it through the facade.
 */
function gsdOffering(Term $term, int $year, string $courseCode): CourseOffering
{
    $semester = Semester::query()->where('term', $term)->where('year', $year)->firstOrFail();

    return CourseOffering::query()
        ->where('semester_id', $semester->getKey())
        ->where('course_id', Course::query()->where('code', $courseCode)->firstOrFail()->getKey())
        ->firstOrFail();
}

it('stands the three completed semesters up in the order the incomplete lifecycle needs', function () {
    $this->seed(DatabaseSeeder::class);

    $starts = Semester::query()->orderBy('starts_at')->get()
        ->map(fn (Semester $semester): string => "{$semester->term->value} {$semester->year}")
        ->all();

    // Chronological by starts_at, which is what supersession and "the following Semester" both
    // order by — never by (year, term), since Fall precedes the following Spring.
    expect($starts)->toBe(['Fall 2024', 'Spring 2025', 'Fall 2025', 'Spring 2026', 'Fall 2026']);

    $fall2024 = Semester::query()->where('term', Term::Fall)->where('year', 2024)->firstOrFail();
    $spring2025 = Semester::query()->where('term', Term::Spring)->where('year', 2025)->firstOrFail();
    $fall2025 = Semester::query()->where('term', Term::Fall)->where('year', 2025)->firstOrFail();

    // All three have finished, so their grades are enterable at all.
    foreach ([$fall2024, $spring2025, $fall2025] as $semester) {
        expect($semester->ends_at->isPast())->toBeTrue();
        expect($semester->grade_submission_deadline_at->greaterThanOrEqualTo($semester->ends_at))->toBeTrue();
    }

    // Exactly one of them is past its grade-submission deadline — the late-flagging subject —
    // while the demo term's deadline is deliberately still ahead, so nothing in it reads as late.
    expect($fall2024->grade_submission_deadline_at->isPast())->toBeTrue();
    expect($spring2025->grade_submission_deadline_at->isFuture())->toBeTrue();
    expect($fall2025->grade_submission_deadline_at->isFuture())->toBeTrue();
});

it('seeds an overdue incomplete and an outstanding one that is not yet overdue', function () {
    $this->seed(DatabaseSeeder::class);

    $deadlines = app(IncompleteDeadlineResolver::class);

    $overdue = Enrollment::query()
        ->where('student_id', gsdStudentId('student.overdue@seed.ejust.test'))
        ->where('course_offering_id', gsdOffering(Term::Spring, 2025, 'CSE512')->getKey())
        ->firstOrFail();
    $outstanding = Enrollment::query()
        ->where('student_id', gsdStudentId('student.incomplete@seed.ejust.test'))
        ->where('course_offering_id', gsdOffering(Term::Fall, 2025, 'CSE512')->getKey())
        ->firstOrFail();

    // Both Enrollments really are Incomplete — the status went through Enrollment's own command,
    // so `pre_incomplete_status` remembers what to restore rather than remembering "Incomplete".
    foreach ([$overdue, $outstanding] as $enrollment) {
        expect($enrollment->course_status->value)->toBe('Incomplete');
        expect($enrollment->pre_incomplete_status?->value)->toBe('Regular');
        expect(Incomplete::query()->where('enrollment_id', $enrollment->getKey())->firstOrFail()->isOutstanding())->toBeTrue();
    }

    // The whole point of the three-semester calendar: one Incomplete is past its derived
    // deadline on seed day and one is not, so the conversion command has both a subject and a
    // control.
    expect($deadlines->forEnrollmentSemester(gsdOffering(Term::Spring, 2025, 'CSE512')->semester_id)?->isPast())->toBeTrue();
    expect($deadlines->forEnrollmentSemester(gsdOffering(Term::Fall, 2025, 'CSE512')->semester_id)?->isFuture())->toBeTrue();

    $this->artisan('grading:convert-expired-incompletes')->assertSuccessful();

    expect($overdue->refresh()->course_status->value)->toBe('FailWithdrawal');
    expect($outstanding->refresh()->course_status->value)->toBe('Incomplete');
});

it('supersedes the earlier attempt of the seeded retake and leaves it visible', function () {
    $this->seed(DatabaseSeeder::class);

    $rania = User::query()->where('email', 'student.retaken@seed.ejust.test')->firstOrFail();

    // Two for-credit Attempts of CSE511, and only the later Semester's counts (spec §Retake
    // supersession) — the earlier D would have dragged the CGPA to 2.00 had it still counted.
    expect(app(GradingRecords::class)->cgpaFor(gsdStudentId('student.retaken@seed.ejust.test')))->toBe(3.00);

    // Read back through the Student's own record, the way My Grades does: the earlier Attempt is
    // still on it — visibly excluded rather than quietly dropped.
    Sanctum::actingAs($rania);
    $attempts = collect($this->getJson('/api/v1/grading/my-grades')->assertOk()->json('data.semesters'))
        ->flatMap(fn (array $semester): array => $semester['courses'])
        ->filter(fn (array $course): bool => $course['course']['code'] === 'CSE511');

    expect($attempts)->toHaveCount(2);
    expect($attempts->where('superseded', true)->pluck('grade.letter_grade')->all())->toBe(['D']);
    expect($attempts->where('superseded', false)->pluck('grade.letter_grade')->all())->toBe(['B']);
});

it('marks the below-C+ preparatory grade as one the student must retake', function () {
    $this->seed(DatabaseSeeder::class);

    Sanctum::actingAs(User::query()->where('email', 'student.mustretake@seed.ejust.test')->firstOrFail());

    $courses = collect($this->getJson('/api/v1/grading/my-grades')->assertOk()->json('data.semesters'))
        ->flatMap(fn (array $semester): array => $semester['courses']);

    // FR-REG-006.5's obligation is a visible marker, not a gate: the D+ on a Preparatory Course
    // earns nothing and carries it; the C+ on the Core Course clears the bar and does not.
    expect($courses->firstWhere('course.code', 'DEP501'))
        ->toMatchArray(['must_retake' => true, 'earned' => false]);
    expect($courses->firstWhere('course.code', 'CSE511'))
        ->toMatchArray(['must_retake' => false, 'earned' => true]);
});

it('rests grade sheets in every state, including a finalized correction round', function () {
    $this->seed(DatabaseSeeder::class);

    $statuses = GradeSheet::query()->pluck('status')->map(fn ($status): string => $status->value)->unique()->sort()->values()->all();
    expect($statuses)->toBe(['Draft', 'Finalized', 'Returned', 'Submitted']);

    // The Returned round carries the note that tells the Instructor what to correct.
    $returned = GradeSheet::query()->where('status', GradeSheetStatus::Returned)->firstOrFail();
    expect($returned->review_note)->not->toBeNull();

    // The headline smoke's Sheet is Draft and blank — exactly what a lazy first open leaves.
    $smokeSheet = GradeSheet::query()
        ->where('course_offering_id', gsdOffering(Term::Spring, 2025, 'CSE531')->getKey())
        ->firstOrFail();
    expect($smokeSheet->status)->toBe(GradeSheetStatus::Draft);
    expect($smokeSheet->grades()->count())->toBe(0);

    // The Correction round is a *second* round on an Offering that already has a Finalized
    // Initial one, and it carries only the line it changes (ADR-0023).
    $corrected = gsdOffering(Term::Fall, 2024, 'CSE511');
    $correction = GradeSheet::query()
        ->where('course_offering_id', $corrected->getKey())
        ->where('kind', GradeSheetKind::Correction)
        ->firstOrFail();
    expect($correction->status)->toBe(GradeSheetStatus::Finalized);
    expect($correction->grades()->count())->toBe(1);

    // The corrected grade is the current one and the earlier finalized C survives as history.
    $farida = gsdStudentId('student.corrected@seed.ejust.test');
    expect(app(GradingRecords::class)->cgpaFor($farida))->toBe(3.30);

    // The late-flagging command's subject: an Offering in a past-deadline Semester whose only
    // round is still awaiting review.
    $late = GradeSheet::query()
        ->where('course_offering_id', gsdOffering(Term::Fall, 2024, 'DEP502')->getKey())
        ->firstOrFail();
    expect($late->status)->toBe(GradeSheetStatus::Submitted);
});

it('routes every department and leaves the uncovered ones visible', function () {
    $this->seed(DatabaseSeeder::class);

    $heads = app(DepartmentHeadAssignments::class);
    $ceId = Course::query()->where('code', 'CSE531')->firstOrFail()->department_id;

    // The Sheets above all route to the CE Head, and the university-wide scope — a NULL
    // Department — is covered by a different Head, which is what lets a Preparatory Sheet be
    // reviewed with no special case.
    expect($heads->headsForDepartment($ceId))->not->toBeEmpty();
    expect($heads->headsForDepartment(null))->not->toBeEmpty();

    // The two scopes are disjoint: a university-wide Head never stands in for a Department.
    expect(array_intersect($heads->headsForDepartment($ceId), $heads->headsForDepartment(null)))->toBe([]);

    // At least one Department is deliberately left without a Head, so the admin screen's
    // "grades would strand here" warning has something real to report.
    $covered = DepartmentHeadAssignment::query()->whereNotNull('department_id')->pluck('department_id')->unique();
    expect($covered->count())->toBeLessThan(Department::query()->count());
});

it('produces the varied student states the demo turns on', function () {
    $this->seed(DatabaseSeeder::class);

    $records = app(GradingRecords::class);

    // Credit-weighted, half-up to two decimals: a D+ over 1 CH beside a C+ over 3 gives 2.20,
    // which is the formula and the rounding exercised in one number.
    expect($records->cgpaFor(gsdStudentId('student.mustretake@seed.ejust.test')))->toBe(2.20);

    // An Incomplete counts toward neither the CGPA nor the earned hours while outstanding.
    expect($records->cgpaFor(gsdStudentId('student.overdue@seed.ejust.test')))->toBe(4.00);
    expect($records->cgpaFor(gsdStudentId('student.incomplete@seed.ejust.test')))->toBe(3.30);

    // The failed external elective still counts in full (FR-REG-006.4's "all grades count"),
    // which is why an F beside a C reads as 1.00 rather than the C alone.
    expect($records->cgpaFor(gsdStudentId('student.external@seed.ejust.test')))->toBe(1.00);

    // The null-CGPA case: Phase 3's smoke Student has Enrollments and no finalized Grade at all,
    // and a fresh record must never read as 0.00.
    expect($records->cgpaFor(gsdStudentId('student.smoke@seed.ejust.test')))->toBeNull();
});

it('seeds idempotently', function () {
    $this->seed(DatabaseSeeder::class);

    $before = [
        'semesters' => Semester::query()->count(),
        'offerings' => CourseOffering::query()->count(),
        'enrollments' => Enrollment::query()->count(),
        'sheets' => GradeSheet::query()->count(),
        'incompletes' => Incomplete::query()->count(),
        'heads' => DepartmentHeadAssignment::query()->count(),
        'statuses' => Enrollment::query()->orderBy('id')->pluck('course_status')->all(),
        'pre_incomplete' => Enrollment::query()->orderBy('id')->pluck('pre_incomplete_status')->all(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect(Semester::query()->count())->toBe($before['semesters']);
    expect(CourseOffering::query()->count())->toBe($before['offerings']);
    expect(Enrollment::query()->count())->toBe($before['enrollments']);
    expect(GradeSheet::query()->count())->toBe($before['sheets']);
    expect(Incomplete::query()->count())->toBe($before['incompletes']);
    expect(DepartmentHeadAssignment::query()->count())->toBe($before['heads']);

    // The subtle one: re-declaring an Incomplete would snapshot "Incomplete" as the status to
    // restore on resolution, silently breaking the lifecycle without changing a single count.
    expect(Enrollment::query()->orderBy('id')->pluck('course_status')->all())->toBe($before['statuses']);
    expect(Enrollment::query()->orderBy('id')->pluck('pre_incomplete_status')->all())->toBe($before['pre_incomplete']);
});
