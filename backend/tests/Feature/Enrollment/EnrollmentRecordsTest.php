<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\AttendanceMark;
use Modules\Enrollment\Internal\Models\ClassSession;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/*
| The Enrollment\Public EnrollmentRecords read facade — the module's Public layer going from
| empty to load-bearing (ADR-0017 deferred it to Phase 4, ADR-0020 is the consumer). Grading
| reads a roster and an Offering's routing facts through this contract and never touches
| Enrollment's tables. Exercised through the **bound contract**, asserting DTOs and null for a
| missing id, never the Eloquent models (ADR-0012). Prior art: CourseCatalogTest.
| Self-contained helpers (er* prefix) so a single-file run works.
*/

function enrollmentRecords(): EnrollmentRecords
{
    return app(EnrollmentRecords::class);
}

/** An Offering in a fresh Semester, taught by the given instructor user id. */
function erOffering(int $instructorId, ?Semester $semester = null): CourseOffering
{
    $semester ??= Semester::factory()->create();
    $course = Course::factory()->create();

    return CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'course_id' => $course->id,
        'instructor_id' => $instructorId,
    ]);
}

/** A Student anchor enrolled in the Offering in the given course status. */
function erEnroll(CourseOffering $offering, CourseStatus $status = CourseStatus::Regular, int $creditHours = 3): Enrollment
{
    $student = Student::factory()->create(['user_id' => User::factory()->create()->id]);

    return Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Core,
        'course_status' => $status,
        'credit_hours' => $creditHours,
    ]);
}

// ---------------------------------------------------------------------------
// Offering reads — the routing and scoping facts
// ---------------------------------------------------------------------------

it('returns an offering summary carrying its semester, course and instructor as opaque ids', function () {
    $instructor = User::factory()->create();
    $offering = erOffering($instructor->id);

    $summary = enrollmentRecords()->findOffering($offering->id);

    expect($summary)->toBeInstanceOf(OfferingSummary::class)
        ->and($summary->id)->toBe($offering->id)
        ->and($summary->semesterId)->toBe($offering->semester_id)
        ->and($summary->courseId)->toBe($offering->course_id)
        ->and($summary->instructorId)->toBe($instructor->id);
});

it('returns null for an offering id that does not exist', function () {
    expect(enrollmentRecords()->findOffering(999_999))->toBeNull();
});

it('lists the offerings one instructor teaches, and no one else\'s', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $a = erOffering($mine->id);
    $b = erOffering($mine->id);
    erOffering($theirs->id);

    $offerings = enrollmentRecords()->offeringsTaughtBy($mine->id);

    expect($offerings)->toHaveCount(2)
        ->and($offerings[0])->toBeInstanceOf(OfferingSummary::class)
        ->and(array_map(fn (OfferingSummary $o): int => $o->id, $offerings))
        ->toEqualCanonicalizing([$a->id, $b->id]);
});

it('returns an empty list for a user who teaches nothing', function () {
    expect(enrollmentRecords()->offeringsTaughtBy(User::factory()->create()->id))->toBe([]);
});

it('lists every offering there is, whoever teaches it — the central picker read', function () {
    // Phase 4 ticket 09: Student Affairs keys grades in for Offerings it does not teach, so this
    // read is deliberately unscoped and the caller does the authorising.
    $mine = erOffering(User::factory()->create()->id);
    $theirs = erOffering(User::factory()->create()->id);

    $offerings = enrollmentRecords()->allOfferings();

    expect($offerings)->toHaveCount(2)
        ->and($offerings[0])->toBeInstanceOf(OfferingSummary::class)
        ->and(array_map(fn (OfferingSummary $o): int => $o->id, $offerings))
        ->toEqualCanonicalizing([$mine->id, $theirs->id]);
});

it('returns an empty list from the central picker read when nothing runs at all', function () {
    expect(enrollmentRecords()->allOfferings())->toBe([]);
});

it('orders both offering lists by the semester that starts latest, not by the newest row', function () {
    // The row created *last* is the *oldest* term — exactly what a back-filled Semester looks
    // like (phase 4 ticket 10's seeder adds three completed terms behind the running one), and
    // exactly what an id-ordered list would get backwards.
    $instructor = User::factory()->create()->id;
    $upcoming = erOffering($instructor, Semester::factory()->create(['starts_at' => now()->addMonths(3)]));
    $running = erOffering($instructor, Semester::factory()->create(['starts_at' => now()->subMonth()]));
    $completed = erOffering($instructor, Semester::factory()->create(['starts_at' => now()->subYear()]));

    $ids = fn (array $offerings): array => array_map(fn (OfferingSummary $o): int => $o->id, $offerings);
    $expected = [$upcoming->id, $running->id, $completed->id];

    expect($ids(enrollmentRecords()->offeringsTaughtBy($instructor)))->toBe($expected)
        ->and($ids(enrollmentRecords()->allOfferings()))->toBe($expected);
});

it('keeps two offerings of one semester in creation order', function () {
    // The tie-break: same Semester, so only the offering id separates them, and it must read
    // ascending even though the whole sort is descending.
    $instructor = User::factory()->create()->id;
    $semester = Semester::factory()->create();
    $first = erOffering($instructor, $semester);
    $second = erOffering($instructor, $semester);

    expect(array_map(
        fn (OfferingSummary $o): int => $o->id,
        enrollmentRecords()->offeringsTaughtBy($instructor),
    ))->toBe([$first->id, $second->id]);
});

// ---------------------------------------------------------------------------
// The gradable roster
// ---------------------------------------------------------------------------

it('returns the gradable enrollments of one offering as DTOs', function () {
    $offering = erOffering(User::factory()->create()->id);
    $enrollment = erEnroll($offering, CourseStatus::Regular, creditHours: 3);

    $roster = enrollmentRecords()->gradableEnrollmentsOf($offering->id);

    expect($roster)->toHaveCount(1)
        ->and($roster[0])->toBeInstanceOf(EnrollmentSummary::class)
        ->and($roster[0]->id)->toBe($enrollment->id)
        ->and($roster[0]->studentId)->toBe($enrollment->student_id)
        ->and($roster[0]->courseOfferingId)->toBe($offering->id)
        ->and($roster[0]->creditHours)->toBe(3);
});

it('exposes the classification and course status as plain strings, never the internal enums', function () {
    $offering = erOffering(User::factory()->create()->id);
    erEnroll($offering, CourseStatus::Retake);

    $roster = enrollmentRecords()->gradableEnrollmentsOf($offering->id);

    expect($roster[0]->classification)->toBe('Core')
        ->and($roster[0]->courseStatus)->toBe('Retake');
});

it('omits Audit and Withdrawn enrollments — an instructor is never asked for a mark that cannot exist', function () {
    $offering = erOffering(User::factory()->create()->id);
    $regular = erEnroll($offering, CourseStatus::Regular);
    erEnroll($offering, CourseStatus::Audit);
    erEnroll($offering, CourseStatus::Withdrawn);

    $roster = enrollmentRecords()->gradableEnrollmentsOf($offering->id);

    expect(array_map(fn (EnrollmentSummary $e): int => $e->id, $roster))->toBe([$regular->id]);
});

it('includes every for-credit attempt kind — Regular, Retake and CGPA Improvement', function () {
    $offering = erOffering(User::factory()->create()->id);
    erEnroll($offering, CourseStatus::Regular);
    erEnroll($offering, CourseStatus::Retake);
    erEnroll($offering, CourseStatus::CgpaImprovement);

    expect(enrollmentRecords()->gradableEnrollmentsOf($offering->id))->toHaveCount(3);
});

it('returns an empty roster for an offering with no gradable enrollments', function () {
    $offering = erOffering(User::factory()->create()->id);
    erEnroll($offering, CourseStatus::Audit);

    expect(enrollmentRecords()->gradableEnrollmentsOf($offering->id))->toBe([]);
});

it('returns an empty roster for an offering id that does not exist', function () {
    expect(enrollmentRecords()->gradableEnrollmentsOf(999_999))->toBe([]);
});

it('never leaks another offering\'s roster', function () {
    $mine = erOffering(User::factory()->create()->id);
    $theirs = erOffering(User::factory()->create()->id);
    erEnroll($mine);
    erEnroll($theirs);

    $roster = enrollmentRecords()->gradableEnrollmentsOf($mine->id);

    expect($roster)->toHaveCount(1)
        ->and($roster[0]->courseOfferingId)->toBe($mine->id);
});

// ---------------------------------------------------------------------------
// findEnrollment / recordOutcome — the command surface ADR-0022 adds (ticket 03)
// ---------------------------------------------------------------------------

it('finds an enrollment by id regardless of gradability', function () {
    $offering = erOffering(User::factory()->create()->id);
    $incomplete = erEnroll($offering, CourseStatus::Incomplete);

    $summary = enrollmentRecords()->findEnrollment($incomplete->id);

    expect($summary)->toBeInstanceOf(EnrollmentSummary::class)
        ->and($summary->id)->toBe($incomplete->id)
        ->and($summary->courseStatus)->toBe('Incomplete');
});

it('returns null for an enrollment id that does not exist', function () {
    expect(enrollmentRecords()->findEnrollment(999_999))->toBeNull();
});

it('resolves several enrollments at once, keyed by id, dropping the ids that resolve to nothing', function () {
    // The `findMany` shape the Catalog facades already use (phase 4.5, ticket 08): a caller
    // holding a set of enrollment ids resolves them in one read rather than in a loop.
    $offering = erOffering(User::factory()->create()->id);
    $regular = erEnroll($offering, CourseStatus::Regular);
    $incomplete = erEnroll($offering, CourseStatus::Incomplete);

    $summaries = enrollmentRecords()->findManyEnrollments([$regular->id, $incomplete->id, 999_999]);

    expect(array_keys($summaries))->toEqualCanonicalizing([$regular->id, $incomplete->id])
        ->and($summaries[$regular->id])->toBeInstanceOf(EnrollmentSummary::class)
        // Unfiltered by gradability, exactly as findEnrollment() is.
        ->and($summaries[$incomplete->id]->courseStatus)->toBe('Incomplete');
});

it('resolves no enrollments for an empty id list', function () {
    expect(enrollmentRecords()->findManyEnrollments([]))->toBe([]);
});

// ---------------------------------------------------------------------------
// enrollmentsForStudent — the CGPA tally's whole input (ticket 04)
// ---------------------------------------------------------------------------

it('returns every enrollment a student has, in every course status, with the CGPA grouping keys', function () {
    $offering = erOffering(User::factory()->create()->id);
    $enrollment = erEnroll($offering, CourseStatus::Regular, creditHours: 3);

    $summary = enrollmentRecords()->enrollmentsForStudent($enrollment->student_id)[0];

    expect($summary)->toBeInstanceOf(EnrollmentSummary::class)
        ->and($summary->id)->toBe($enrollment->id)
        ->and($summary->courseId)->toBe($offering->course_id)
        ->and($summary->semesterId)->toBe($offering->semester_id)
        ->and($summary->countsTowardCgpa)->toBeTrue()
        ->and($summary->earned)->toBeNull();
});

it('reads countsTowardCgpa from the course status counting matrix, never re-deriving it', function () {
    $offering = erOffering(User::factory()->create()->id);
    $regular = erEnroll($offering, CourseStatus::Regular);
    $audit = erEnroll($offering, CourseStatus::Audit);

    $byId = collect(enrollmentRecords()->enrollmentsForStudent($regular->student_id))
        ->keyBy(fn (EnrollmentSummary $e): int => $e->id);

    expect($byId[$regular->id]->countsTowardCgpa)->toBeTrue();

    $auditSummary = collect(enrollmentRecords()->enrollmentsForStudent($audit->student_id))
        ->keyBy(fn (EnrollmentSummary $e): int => $e->id);
    expect($auditSummary[$audit->id]->countsTowardCgpa)->toBeFalse();
});

it('carries the earned bit once recordOutcome has written it', function () {
    $offering = erOffering(User::factory()->create()->id);
    $enrollment = erEnroll($offering);
    enrollmentRecords()->recordOutcome($enrollment->id, true);

    $summary = enrollmentRecords()->enrollmentsForStudent($enrollment->student_id)[0];

    expect($summary->earned)->toBeTrue();
});

it('returns an empty list for a student with no enrollments at all — the null-CGPA case', function () {
    $student = Student::factory()->create(['user_id' => User::factory()->create()->id]);

    expect(enrollmentRecords()->enrollmentsForStudent($student->id))->toBe([]);
});

it('returns an empty list for a student id that does not exist', function () {
    expect(enrollmentRecords()->enrollmentsForStudent(999_999))->toBe([]);
});

it('never leaks another student\'s enrollments', function () {
    $offering = erOffering(User::factory()->create()->id);
    $mine = erEnroll($offering);
    erEnroll($offering);

    $summaries = enrollmentRecords()->enrollmentsForStudent($mine->student_id);

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->id)->toBe($mine->id);
});

it('writes the earned bit through recordOutcome, the sole writer of the column', function () {
    $offering = erOffering(User::factory()->create()->id);
    $enrollment = erEnroll($offering);

    enrollmentRecords()->recordOutcome($enrollment->id, true);
    expect($enrollment->fresh()->earned)->toBeTrue();

    enrollmentRecords()->recordOutcome($enrollment->id, false);
    expect($enrollment->fresh()->earned)->toBeFalse();

    enrollmentRecords()->recordOutcome($enrollment->id, null);
    expect($enrollment->fresh()->earned)->toBeNull();
});

it('bulk resolves bounded student rosters as owner DTOs including empty and non-counting rosters', function () {
    $instructor = User::factory()->create();
    $first = erEnroll(erOffering($instructor->id));
    $second = erEnroll(erOffering($instructor->id), CourseStatus::Audit);
    $empty = Student::factory()->create();
    $ids = [$first->student_id, $second->student_id, $empty->id];
    $batch = enrollmentRecords()->enrollmentsForStudents($ids);
    expect(array_keys($batch))->toBe($ids)->and($batch[$empty->id])->toBe([])
        ->and($batch[$first->student_id])->toEqual(enrollmentRecords()->enrollmentsForStudent($first->student_id))
        ->and($batch[$second->student_id][0])->toBeInstanceOf(EnrollmentSummary::class)
        ->and($batch[$second->student_id][0]->countsTowardCgpa)->toBeFalse();
});

it('batches owner attendance and enrollment counts excluding cancelled sessions and preserving no data', function () {
    $offering = erOffering(User::factory()->create()->id);
    $audit = erEnroll($offering, CourseStatus::Audit);
    $unmarked = erEnroll($offering);
    $session = ClassSession::factory()->create(['course_offering_id' => $offering->id]);
    $cancelled = ClassSession::factory()->cancelled()->on('2026-09-14')->create(['course_offering_id' => $offering->id]);
    AttendanceMark::factory()->create(['class_session_id' => $session->id, 'enrollment_id' => $audit->id, 'present' => false]);
    AttendanceMark::factory()->create(['class_session_id' => $cancelled->id, 'enrollment_id' => $audit->id, 'present' => true]);
    $attendance = enrollmentRecords()->attendanceForEnrollments([$audit->id, $unmarked->id, 999999]);
    expect($attendance[$audit->id]->recorded)->toBe(1)->and($attendance[$audit->id]->present)->toBe(0)
        ->and($attendance[$audit->id]->percentage)->toBe(0.0)->and($attendance[$audit->id]->atRisk)->toBeTrue()
        ->and($attendance[$unmarked->id]->percentage)->toBeNull()->and($attendance)->not->toHaveKey(999999);
    expect(enrollmentRecords()->enrollmentCountsForStudents([$audit->student_id, $unmarked->student_id, 999999]))
        ->toBe([$audit->student_id => 1, $unmarked->student_id => 1, 999999 => 0]);
    expect(enrollmentRecords()->enrollmentCountsForStudents([$audit->student_id], 999999))->toBe([$audit->student_id => 0]);
});
