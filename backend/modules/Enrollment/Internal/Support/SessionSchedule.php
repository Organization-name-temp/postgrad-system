<?php

namespace Modules\Enrollment\Internal\Support;

use Carbon\CarbonInterface;
use Modules\Enrollment\Internal\Enums\Weekday;
use Modules\Enrollment\Internal\Models\ClassSession;

/**
 * Generates a Course Offering's {@see ClassSession}
 * descriptors from its weekly meeting rows across a Semester's start→end dates (ADR-0019,
 * ticket 08). This is the single seam a future timetabler would replace: it reads nothing
 * but the meeting slots and the two dates and returns plain descriptors, so the
 * AttendanceController can persist them idempotently and the arithmetic is unit-tested
 * without the database.
 *
 * Weekday integers follow Carbon's `dayOfWeek` numbering (Sunday 0 … Saturday 6) — the same
 * numbering the {@see Weekday} enum uses — so a meeting's
 * weekday matches a calendar date's directly. The range is inclusive of both endpoints; the
 * result is ordered by date, then by start time within a date.
 */
final class SessionSchedule
{
    /**
     * The session descriptors for the given meetings across `[start, end]` (inclusive).
     * Empty when there are no meetings or the range is inverted.
     *
     * @param  list<array{weekday: int, starts_at: string, ends_at: string}>  $meetings
     * @return list<array{date: string, weekday: int, starts_at: string, ends_at: string}>
     */
    public static function generate(CarbonInterface $start, CarbonInterface $end, array $meetings): array
    {
        if ($meetings === []) {
            return [];
        }

        $from = $start->copy()->startOfDay();
        $to = $end->copy()->startOfDay();
        if ($from->greaterThan($to)) {
            return [];
        }

        // Group the meetings by weekday so each date is matched in one pass, and keep each
        // day's slots ordered by start time for a stable register layout.
        $byWeekday = [];
        foreach ($meetings as $meeting) {
            $byWeekday[$meeting['weekday']][] = $meeting;
        }
        foreach ($byWeekday as &$slots) {
            usort($slots, static fn (array $a, array $b): int => strcmp($a['starts_at'], $b['starts_at']));
        }
        unset($slots);

        $sessions = [];
        for ($date = $from; $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
            foreach ($byWeekday[$date->dayOfWeek] ?? [] as $slot) {
                $sessions[] = [
                    'date' => $date->toDateString(),
                    'weekday' => $slot['weekday'],
                    'starts_at' => $slot['starts_at'],
                    'ends_at' => $slot['ends_at'],
                ];
            }
        }

        return $sessions;
    }
}
