<?php

use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Support\SessionSchedule;

/*
| Class-session generation, unit-tested directly (ticket 08, spec §Testing Decisions —
| the pure "session generation from meeting rows" seam beside CreditHours / MeetingTime).
| It walks a Semester's start→end dates and emits one session descriptor per date whose
| weekday matches a weekly meeting slot; the AttendanceController persists those rows
| idempotently. A pure value — no clock, no database — so the calendar arithmetic is
| tested without an HTTP round-trip.
|
| Weekday integers follow Carbon's dayOfWeek numbering (Sunday 0 … Saturday 6), the same
| numbering the Weekday enum uses, so a meeting's weekday matches a date's directly.
*/

/** @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $meetings */
function generateSessions(string $start, string $end, array $meetings): array
{
    return SessionSchedule::generate(Carbon::parse($start), Carbon::parse($end), $meetings);
}

it('emits one session per matching weekday across the term for a single weekly meeting', function () {
    // Mondays between Sep 1 (a Tuesday) and Sep 30 2026: 7, 14, 21, 28 — four Mondays.
    $sessions = generateSessions('2026-09-01', '2026-09-30', [
        ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '11:00'],
    ]);

    expect($sessions)->toHaveCount(4)
        ->and(array_column($sessions, 'date'))->toBe([
            '2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28',
        ])
        ->and($sessions[0])->toMatchArray([
            'date' => '2026-09-07',
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '11:00',
        ]);
});

it('includes a meeting that falls exactly on the start or end date (inclusive range)', function () {
    // Sep 7 2026 is a Monday (== start); Sep 28 is a Monday (< end Sep 28 inclusive).
    $sessions = generateSessions('2026-09-07', '2026-09-28', [
        ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '11:00'],
    ]);

    expect(array_column($sessions, 'date'))->toBe([
        '2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28',
    ]);
});

it('interleaves multiple weekly meetings in date then start-time order', function () {
    // A Monday 09:00 slot and a Wednesday 13:00 slot over one week (Sep 7 Mon, Sep 9 Wed).
    $sessions = generateSessions('2026-09-07', '2026-09-11', [
        ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '11:00'],
        ['weekday' => 3, 'starts_at' => '13:00', 'ends_at' => '15:00'],
    ]);

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0])->toMatchArray(['date' => '2026-09-07', 'weekday' => 1])
        ->and($sessions[1])->toMatchArray(['date' => '2026-09-09', 'weekday' => 3]);
});

it('orders two same-day meetings by their start time', function () {
    // Two Monday slots on Sep 7: 13:00 then 09:00 given out of order → emitted 09:00 first.
    $sessions = generateSessions('2026-09-07', '2026-09-07', [
        ['weekday' => 1, 'starts_at' => '13:00', 'ends_at' => '15:00'],
        ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '11:00'],
    ]);

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['starts_at'])->toBe('09:00')
        ->and($sessions[1]['starts_at'])->toBe('13:00');
});

it('emits nothing when no meeting weekday occurs in the range', function () {
    // Only a Friday (5) meeting, but Sep 7–10 2026 is Mon–Thu.
    $sessions = generateSessions('2026-09-07', '2026-09-10', [
        ['weekday' => 5, 'starts_at' => '09:00', 'ends_at' => '11:00'],
    ]);

    expect($sessions)->toBe([]);
});

it('emits nothing for no meetings or an inverted range', function () {
    expect(generateSessions('2026-09-01', '2026-12-20', []))->toBe([])
        ->and(generateSessions('2026-12-20', '2026-09-01', [
            ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '11:00'],
        ]))->toBe([]);
});
