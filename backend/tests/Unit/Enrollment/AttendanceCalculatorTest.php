<?php

use Modules\Enrollment\Internal\Support\AttendanceCalculator;

/*
| The attendance arithmetic, unit-tested directly (ticket 08, spec §Testing Decisions —
| the pure "attendance percentage + crossing detection" seam). Two pure answers with no
| stored state (ADR-0019): the derived percentage (present ÷ recorded, null when nothing
| is recorded) and whether a save took an enrollment on a *downward* crossing below the
| audit threshold — the single trigger for the audit-at-risk notification, computed by
| comparing the percentage before and after each register save so it fires once per
| crossing and never on every mark.
*/

it('derives the percentage as present over recorded', function () {
    expect(AttendanceCalculator::percentage(4, 5))->toBe(80.0)
        ->and(AttendanceCalculator::percentage(3, 4))->toBe(75.0)
        ->and(AttendanceCalculator::percentage(0, 2))->toBe(0.0)
        ->and(AttendanceCalculator::percentage(2, 3))->toEqualWithDelta(66.666, 0.01);
});

it('has no percentage when nothing is recorded (null, never division by zero)', function () {
    expect(AttendanceCalculator::percentage(0, 0))->toBeNull();
});

it('detects a downward crossing below the threshold', function () {
    // Was at/above 75, now below → crossed.
    expect(AttendanceCalculator::crossedBelow(80.0, 66.6, 75.0))->toBeTrue()
        ->and(AttendanceCalculator::crossedBelow(75.0, 74.9, 75.0))->toBeTrue();
});

it('treats going from no data straight to below-threshold as a crossing', function () {
    // First recorded session is an absence → null before, below after → crossed.
    expect(AttendanceCalculator::crossedBelow(null, 0.0, 75.0))->toBeTrue();
});

it('does not fire when already below the threshold before the save', function () {
    // Still below, but it was already below → no fresh crossing (no spam).
    expect(AttendanceCalculator::crossedBelow(60.0, 50.0, 75.0))->toBeFalse();
});

it('does not fire when the percentage stays at or above the threshold', function () {
    expect(AttendanceCalculator::crossedBelow(100.0, 80.0, 75.0))->toBeFalse()
        ->and(AttendanceCalculator::crossedBelow(80.0, 75.0, 75.0))->toBeFalse() // exactly 75 is not below
        ->and(AttendanceCalculator::crossedBelow(null, 100.0, 75.0))->toBeFalse();
});

it('fires again on a fresh downward crossing after recovering above the threshold', function () {
    // Recovered to 80 then dipped to 70 → a new downward crossing fires again.
    expect(AttendanceCalculator::crossedBelow(80.0, 70.0, 75.0))->toBeTrue();
});

it('never fires when the after value is unknown', function () {
    expect(AttendanceCalculator::crossedBelow(50.0, null, 75.0))->toBeFalse();
});
