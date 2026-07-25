<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Catalog\Internal\Models\Course;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
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
