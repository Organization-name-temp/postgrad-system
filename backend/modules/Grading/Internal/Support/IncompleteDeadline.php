<?php

namespace Modules\Grading\Internal\Support;

use Carbon\CarbonImmutable;

/**
 * The pure Incomplete-deadline arithmetic (SRS §4.7.3.2, ticket 05): "week 1 begins on the
 * following Semester's `starts_at`, so week 10 begins on day 63." No database, no HTTP — the
 * only input is the next Semester's `starts_at`, resolved by {@see IncompleteDeadlineResolver}
 * through the Calendar facade, the same discipline {@see GpaCalculator} follows for its own
 * arithmetic.
 *
 * The deadline is never stored (ADR-0018): recomputing it from the current `starts_at` on
 * every read is what makes correcting a Semester's start date move every outstanding deadline
 * with it, for free.
 */
final class IncompleteDeadline
{
    /** SRS §4.7.3.2's ten weeks: week 1 begins on day 0, so week 10 begins on day 63. */
    private const RESOLUTION_WINDOW_DAYS = 63;

    /** The resolution deadline given the Semester following the Enrollment's `starts_at`. */
    public static function forNextSemesterStart(CarbonImmutable $nextSemesterStartsAt): CarbonImmutable
    {
        return $nextSemesterStartsAt->addDays(self::RESOLUTION_WINDOW_DAYS);
    }
}
