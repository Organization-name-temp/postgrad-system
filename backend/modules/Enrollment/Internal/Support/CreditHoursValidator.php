<?php

namespace Modules\Enrollment\Internal\Support;

/**
 * The pure credit-hour load validator (FR-REG-001, spec §Rules engines) — the single
 * home for the min-6 / max-12 rule a registration cart is checked against. It counts the
 * credit hours of every counted line (Regular + Audit both load, Appendix D) and reports
 * whether the total sits below the floor or above the ceiling, honouring the
 * {@see CreditHoursExceptions} that may waive the floor.
 *
 * Kept as a small pure value object (the {@see MeetingTime}/{@see EligibilityClassifier}
 * precedent) so the whole rule is unit-testable without the HTTP stack, the database, or
 * the config — the limits are injected. The cart submission (ticket 05) builds one from
 * {@see self::fromConfig()}; the advisor decision (ticket 06) re-validates the approved
 * subset with the very same seam, so the rule is expressed once.
 */
final readonly class CreditHoursValidator
{
    public function __construct(
        public int $minCreditHours,
        public int $maxCreditHours,
    ) {}

    /**
     * Build the validator from `config/enrollment.php` — the env-overridable limits the
     * live registration surface enforces.
     */
    public static function fromConfig(): self
    {
        return new self(
            minCreditHours: (int) config('enrollment.min_credit_hours'),
            maxCreditHours: (int) config('enrollment.max_credit_hours'),
        );
    }

    /**
     * Validate a cart's credit-hour load. Sums the per-line credit hours (each Regular or
     * Audit line contributes its course's total CH) and reports the floor/ceiling
     * outcome: the ceiling always bites; the floor is skipped when the exceptions waive it
     * (a first-semester registration may carry a reduced load).
     *
     * @param  list<int>  $creditHours  one entry per counted cart line — the course's total CH
     */
    public function validate(array $creditHours, CreditHoursExceptions $exceptions): CreditHoursValidation
    {
        $total = array_sum($creditHours);

        return new CreditHoursValidation(
            total: $total,
            belowMinimum: ! $exceptions->waivesFloor() && $total < $this->minCreditHours,
            aboveMaximum: $total > $this->maxCreditHours,
        );
    }
}
