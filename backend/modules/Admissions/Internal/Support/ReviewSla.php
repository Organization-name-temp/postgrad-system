<?php

namespace Modules\Admissions\Internal\Support;

use Carbon\CarbonInterface;
use Modules\Admissions\Internal\Console\Commands\FlagOverdueApplications;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;

/**
 * One review stage's SLA — how long an Application may wait in it before it counts as overdue, and
 * how long a given Application actually *has* waited (spec §SLAs, ticket 11).
 *
 * Extracted here (phase 4.5, ticket 03) so the overdue-flagging command
 * ({@see FlagOverdueApplications}) and the Screening Queue Dashboard Card read the **same** two
 * rules rather than two implementations of them: the age is {@see BusinessDays} — the working week
 * is Sunday–Thursday, so the Egyptian Friday/Saturday weekend does not count — and the threshold is
 * the configured per-stage SLA. A Card must agree with the notification a reviewer already got.
 *
 * Age is **derived on demand** and never stored: it is measured from the `occurred_at` of the
 * timeline entry into the stage, so each read re-reads the current state. Overdue is *information
 * only* — nothing here escalates, changes a status, or appends a timeline row, exactly as the
 * hand-run command does not.
 */
final readonly class ReviewSla
{
    private function __construct(
        /** The review stage this SLA governs — also how an Application's entry into it is found. */
        public ApplicationStatus $status,
        /** Business days an Application may wait in the stage before it is overdue. */
        public int $slaDays,
    ) {}

    /** The Student Affairs screening stage (`under_sa_review`). */
    public static function screening(): self
    {
        return new self(
            ApplicationStatus::UnderSAReview,
            (int) config('admissions.sa_screening_sla_days'),
        );
    }

    /** The Dean review stage (`under_dean_review`). */
    public static function deanReview(): self
    {
        return new self(
            ApplicationStatus::UnderDeanReview,
            (int) config('admissions.dean_review_sla_days'),
        );
    }

    /**
     * How many business days the Application has waited in this stage, or null when it has no
     * timeline entry into the stage to measure from (nothing to age). Requires the
     * `statusTransitions` relation to be loaded — aging is derived from the timeline, never stored.
     */
    public function ageInDaysOf(Application $application, CarbonInterface $now): ?int
    {
        $enteredAt = $application->enteredStatusAt($this->status);

        return $enteredAt === null ? null : BusinessDays::between($enteredAt, $now);
    }

    /**
     * Whether an age has passed the SLA — strictly greater, so an Application on its last allowed
     * day is still in time. A null age (no entry to measure from) is never overdue.
     */
    public function isOverdue(?int $ageInDays): bool
    {
        return $ageInDays !== null && $ageInDays > $this->slaDays;
    }
}
