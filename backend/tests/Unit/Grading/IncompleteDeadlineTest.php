<?php

use Carbon\CarbonImmutable;
use Modules\Grading\Internal\Support\IncompleteDeadline;

/*
| Ticket 05 — the derived Incomplete-resolution deadline (SRS §4.7.3.2): "week 1 begins on the
| following Semester's starts_at, so week 10 begins on day 63." Pure arithmetic, no database —
| the Calendar lookup that finds "the next Semester" is exercised at the Feature seam
| (IncompleteLifecycleTest), never here.
*/

it('adds exactly 63 days to the next semester\'s start — a Spring enrollment, Fall next', function () {
    // The Enrollment was taken in Spring 2026; the following Semester is Fall 2026.
    $fallStart = CarbonImmutable::parse('2026-09-01T00:00:00Z');

    $deadline = IncompleteDeadline::forNextSemesterStart($fallStart);

    expect((int) $fallStart->diffInDays($deadline))->toBe(63)
        ->and($deadline->toDateString())->toBe('2026-11-03');
});

it('adds exactly 63 days to the next semester\'s start — a Fall enrollment, Spring next', function () {
    // The Enrollment was taken in Fall 2026; the following Semester is Spring 2027 — across
    // the year boundary, exercising the same arithmetic in the other direction.
    $springStart = CarbonImmutable::parse('2027-01-15T00:00:00Z');

    $deadline = IncompleteDeadline::forNextSemesterStart($springStart);

    expect((int) $springStart->diffInDays($deadline))->toBe(63)
        ->and($deadline->toDateString())->toBe('2027-03-19');
});

it('preserves the time of day, never just the date', function () {
    $nextStart = CarbonImmutable::parse('2026-09-01T08:30:00Z');

    $deadline = IncompleteDeadline::forNextSemesterStart($nextStart);

    expect($deadline->format('H:i:s'))->toBe('08:30:00');
});
