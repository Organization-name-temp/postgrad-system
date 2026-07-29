<?php

namespace Modules\Grading\Internal\Support;

use Carbon\CarbonImmutable;
use Modules\Calendar\Public\AcademicCalendar;

/**
 * Resolves one Enrollment's Incomplete-resolution deadline through the Calendar facade
 * (ticket 05): the Semester following the Enrollment's own, plus 63 days
 * ({@see IncompleteDeadline}). `null` when the next Semester does not exist in the Calendar
 * yet — an admin's omission that never blocks declaring the Incomplete, and that the
 * conversion command skips and reports rather than crashing on (spec §Incomplete and Fail
 * Withdrawal).
 *
 * Reads `Calendar\Public` fresh on every call — never cached — so a later correction to the
 * next Semester's `starts_at` moves the deadline with it (ADR-0018).
 */
final readonly class IncompleteDeadlineResolver
{
    public function __construct(private AcademicCalendar $calendar) {}

    /** The Incomplete deadline for an Enrollment whose original Offering ran in `$semesterId`. */
    public function forEnrollmentSemester(int $semesterId): ?CarbonImmutable
    {
        $next = $this->calendar->nextAfter($semesterId);

        return $next === null ? null : IncompleteDeadline::forNextSemesterStart($next->startsAt);
    }
}
