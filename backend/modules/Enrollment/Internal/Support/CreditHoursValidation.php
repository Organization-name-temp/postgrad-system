<?php

namespace Modules\Enrollment\Internal\Support;

/**
 * The outcome of running a cart's credit-hour load past the {@see CreditHoursValidator}:
 * the counted total, and whether it fell below the (possibly waived) floor and/or above
 * the ceiling. Two independent flags rather than one so the caller can enumerate exactly
 * which limit a submission broke in its 422 (spec §Registration workflow); the total is
 * carried so the caller can name it in the message without re-summing. An immutable value
 * object.
 */
final readonly class CreditHoursValidation
{
    public function __construct(
        public int $total,
        public bool $belowMinimum,
        public bool $aboveMaximum,
    ) {}

    /** Whether the load is within limits — neither below the floor nor above the ceiling. */
    public function passes(): bool
    {
        return ! $this->belowMinimum && ! $this->aboveMaximum;
    }
}
