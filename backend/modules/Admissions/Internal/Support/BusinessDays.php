<?php

namespace Modules\Admissions\Internal\Support;

use Carbon\CarbonInterface;

/**
 * Business-day arithmetic for the review SLAs (ticket 11, spec §SLAs): the working
 * week is Sunday–Thursday, so a day counts unless it is a **Friday or Saturday** (the
 * Egyptian weekend). There is deliberately no public-holiday calendar in this build.
 *
 * A pure helper — no state, no clock of its own — so the caller passes the two moments
 * and it is unit-tested directly. The overdue-flagging command uses it to derive how
 * long an application has waited in a review stage (the `occurred_at` of the transition
 * that entered the stage → now), which is why the age is never stored: it is recomputed
 * on demand each run.
 */
final class BusinessDays
{
    /**
     * The number of business days elapsed from `$from` to `$to` — each whole day that
     * has begun since `$from` and is neither a Friday nor a Saturday. Zero when `$to`
     * is at or before `$from` (nothing has elapsed). Time-of-day is significant only in
     * that a full 24 hours must pass to advance a day; the weekend is decided by the
     * day the cursor lands on.
     */
    public static function between(CarbonInterface $from, CarbonInterface $to): int
    {
        if ($to->lessThanOrEqualTo($from)) {
            return 0;
        }

        $count = 0;
        $cursor = $from->copy();

        while ($cursor->lessThan($to)) {
            $cursor = $cursor->addDay();

            if (! $cursor->isFriday() && ! $cursor->isSaturday()) {
                $count++;
            }
        }

        return $count;
    }
}
