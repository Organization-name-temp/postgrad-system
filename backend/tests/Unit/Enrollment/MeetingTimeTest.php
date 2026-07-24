<?php

use Modules\Enrollment\Internal\Support\MeetingTime;

/**
 * The interval-overlap seam, unit-tested directly (the one piece of pure Enrollment
 * scheduling arithmetic that earns a test below the HTTP seam — spec §Testing
 * Decisions, the CreditHours precedent). It answers "do two weekly meetings clash?"
 * for both the offering self-overlap check (ticket 03) and, later, student schedule
 * clashes — so the OfferingController rejects overlapping meetings with a 422 using
 * exactly this predicate.
 */
it('overlaps two slots on the same weekday whose times intersect', function () {
    $a = MeetingTime::fromClock(1, '09:00', '11:00');
    $b = MeetingTime::fromClock(1, '10:00', '12:00');

    expect($a->overlaps($b))->toBeTrue()
        ->and($b->overlaps($a))->toBeTrue();
});

it('does not overlap slots on different weekdays even at the same time', function () {
    $a = MeetingTime::fromClock(1, '09:00', '11:00');
    $b = MeetingTime::fromClock(2, '09:00', '11:00');

    expect($a->overlaps($b))->toBeFalse();
});

it('treats touching endpoints as non-overlapping (half-open intervals)', function () {
    $a = MeetingTime::fromClock(3, '09:00', '11:00');
    $b = MeetingTime::fromClock(3, '11:00', '13:00');

    expect($a->overlaps($b))->toBeFalse()
        ->and($b->overlaps($a))->toBeFalse();
});

it('overlaps when one slot fully contains another', function () {
    $outer = MeetingTime::fromClock(0, '08:00', '16:00');
    $inner = MeetingTime::fromClock(0, '10:00', '11:00');

    expect($outer->overlaps($inner))->toBeTrue()
        ->and($inner->overlaps($outer))->toBeTrue();
});

it('parses clock strings to minutes from midnight', function () {
    $slot = MeetingTime::fromClock(4, '13:30', '15:45');

    expect($slot->weekday)->toBe(4)
        ->and($slot->startMinute)->toBe(13 * 60 + 30)
        ->and($slot->endMinute)->toBe(15 * 60 + 45);
});

it('is well-formed only when it ends after it starts', function () {
    expect(MeetingTime::fromClock(1, '09:00', '11:00')->isWellFormed())->toBeTrue()
        ->and(MeetingTime::fromClock(1, '11:00', '11:00')->isWellFormed())->toBeFalse()
        ->and(MeetingTime::fromClock(1, '12:00', '11:00')->isWellFormed())->toBeFalse();
});

it('detects whether any two slots in a set clash', function () {
    $noClash = [
        MeetingTime::fromClock(1, '09:00', '11:00'),
        MeetingTime::fromClock(1, '11:00', '13:00'), // touches, does not clash
        MeetingTime::fromClock(2, '09:00', '11:00'), // different day
    ];

    $withClash = [
        MeetingTime::fromClock(1, '09:00', '11:00'),
        MeetingTime::fromClock(3, '09:00', '11:00'),
        MeetingTime::fromClock(1, '10:30', '12:00'), // clashes with the first slot
    ];

    expect(MeetingTime::hasOverlap($noClash))->toBeFalse()
        ->and(MeetingTime::hasOverlap($withClash))->toBeTrue();
});
