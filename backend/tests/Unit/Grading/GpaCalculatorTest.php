<?php

use Carbon\CarbonImmutable;
use Modules\Grading\Internal\Support\GpaAttempt;
use Modules\Grading\Internal\Support\GpaCalculator;

/*
| The pure GPA/CGPA arithmetic (ADR-0021, spec §GPA and CGPA, ticket 04), unit-tested
| directly — no HTTP stack and no database, the AttendanceCalculator precedent. Every
| GpaAttempt here is a bare value object; {@see GpaCalculator} never touches Enrollment,
| Calendar or the database, so a fixture is just a courseId/semesterId/starts_at/creditHours/
| gpaPoints tuple.
*/

/** A GpaAttempt with sensible defaults, overridable per test. */
function gcAttempt(
    int $courseId,
    float $points,
    int $creditHours = 3,
    int $enrollmentId = 0,
    int $semesterId = 1,
    ?CarbonImmutable $semesterStartsAt = null,
): GpaAttempt {
    return new GpaAttempt(
        enrollmentId: $enrollmentId,
        courseId: $courseId,
        semesterId: $semesterId,
        semesterStartsAt: $semesterStartsAt ?? CarbonImmutable::parse('2026-01-01'),
        creditHours: $creditHours,
        gpaPoints: $points,
    );
}

// ---------------------------------------------------------------------------
// average() — the credit-weighted formula and its rounding
// ---------------------------------------------------------------------------

it('is credit-weighted, not a plain mean — a 1-CH attempt must not weigh as much as a 3-CH one', function () {
    // Plain mean of 4.00 and 2.00 would be 3.00; weighted by credit hours it is 2.50.
    $attempts = [
        gcAttempt(courseId: 1, points: 4.00, creditHours: 1),
        gcAttempt(courseId: 2, points: 2.00, creditHours: 3),
    ];

    expect(GpaCalculator::average($attempts))->toBe(2.50);
});

it('counts every letter down to F at 0.00', function () {
    $attempts = [
        gcAttempt(courseId: 1, points: 4.00, creditHours: 3),
        gcAttempt(courseId: 2, points: 0.00, creditHours: 3),
    ];

    expect(GpaCalculator::average($attempts))->toBe(2.00);
});

it('is null — never 0.00 — with no attempts, so a fresh record never looks catastrophic', function () {
    expect(GpaCalculator::average([]))->toBeNull();
});

it('rounds the quotient half-up to two decimals exactly once, and a value can round across the 3.0 boundary', function () {
    // 13 CH at 3.30 + 8 CH at 2.50 over 21 CH = 6290/21 = 299.523809... hundredths: the raw
    // average is below 3.00, but rounds UP to exactly 3.00 — the single rounded figure the
    // spec requires display and every threshold decision (e.g. the 3.0 graduation CGPA) to
    // agree on, never the unrounded 2.9952...
    $attempts = [
        gcAttempt(courseId: 1, points: 3.30, creditHours: 13),
        gcAttempt(courseId: 2, points: 2.50, creditHours: 8),
    ];

    expect(GpaCalculator::average($attempts))->toBe(3.00);
});

it('has no floating-point drift across many attempts', function () {
    // 10 attempts of 1 CH at 1.30 (D+): a naive running float sum could drift off 13.00/10.
    $attempts = array_map(
        fn (int $i): GpaAttempt => gcAttempt(courseId: $i, points: 1.30, creditHours: 1),
        range(1, 10),
    );

    expect(GpaCalculator::average($attempts))->toBe(1.30);
});

// ---------------------------------------------------------------------------
// nonSuperseded() — the Retake supersession rule
// ---------------------------------------------------------------------------

it('keeps only the chronologically latest attempt of a course, by semester starts_at, crossing a year boundary', function () {
    // Fall 2026 (starts_at Sept 2026) then Spring 2027 (starts_at Feb 2027) — a real
    // cross-year pair, never ordered by a (year, term) tuple.
    $fall2026 = gcAttempt(courseId: 1, points: 2.00, semesterId: 10, semesterStartsAt: CarbonImmutable::parse('2026-09-01'));
    $spring2027 = gcAttempt(courseId: 1, points: 3.30, semesterId: 20, semesterStartsAt: CarbonImmutable::parse('2027-02-01'));

    expect(GpaCalculator::nonSuperseded([$fall2026, $spring2027]))->toBe([$spring2027]);
});

it('lets a worse Retake lower the CGPA — "latest" is unconditional, never "best attempt"', function () {
    $first = gcAttempt(courseId: 1, points: 4.00, semesterId: 10, semesterStartsAt: CarbonImmutable::parse('2026-09-01'));
    $retake = gcAttempt(courseId: 1, points: 1.00, semesterId: 20, semesterStartsAt: CarbonImmutable::parse('2027-02-01'));

    $eligible = GpaCalculator::nonSuperseded([$first, $retake]);

    expect($eligible)->toBe([$retake])
        ->and(GpaCalculator::average($eligible))->toBe(1.00);
});

it('never supersedes across two different Courses — both electives count (FR-REG-006.4)', function () {
    $electiveA = gcAttempt(courseId: 1, points: 1.00);
    $electiveB = gcAttempt(courseId: 2, points: 4.00);

    expect(GpaCalculator::nonSuperseded([$electiveA, $electiveB]))->toEqualCanonicalizing([$electiveA, $electiveB]);
});

it('excludes a superseded attempt from its own original Semester GPA, not only the CGPA', function () {
    $originalAttempt = gcAttempt(courseId: 1, points: 4.00, creditHours: 3, semesterId: 100, semesterStartsAt: CarbonImmutable::parse('2026-09-01'));
    $retake = gcAttempt(courseId: 1, points: 1.00, creditHours: 3, semesterId: 200, semesterStartsAt: CarbonImmutable::parse('2027-02-01'));
    $unrelatedElective = gcAttempt(courseId: 2, points: 3.00, creditHours: 3, semesterId: 100, semesterStartsAt: CarbonImmutable::parse('2026-09-01'));

    $eligible = GpaCalculator::nonSuperseded([$originalAttempt, $retake, $unrelatedElective]);

    // The CGPA: the retake (1.00) and the untouched elective (3.00), credit-weighted.
    expect(GpaCalculator::average($eligible))->toBe(2.00);

    // Semester 100's GPA is the elective alone (3.00) — the superseded attempt does not drag
    // its own original semester's average down to a blend with a course it no longer counts
    // toward, even though it happened in that same semester.
    $semester100 = array_values(array_filter($eligible, fn (GpaAttempt $a): bool => $a->semesterId === 100));
    expect(GpaCalculator::average($semester100))->toBe(3.00);

    // Semester 200 (the retake's own semester) carries only the retake.
    $semester200 = array_values(array_filter($eligible, fn (GpaAttempt $a): bool => $a->semesterId === 200));
    expect(GpaCalculator::average($semester200))->toBe(1.00);
});
