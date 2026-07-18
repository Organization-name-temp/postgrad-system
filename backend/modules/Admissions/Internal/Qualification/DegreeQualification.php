<?php

namespace Modules\Admissions\Internal\Qualification;

use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Catalog\Public\Enums\DegreeLevel;

/**
 * The degree-qualification rule (FR-ADM-004.4, spec §Implementation Decisions):
 * the degrees are ordered BSc < MSc < PhD, and an applicant qualifies for a target
 * level iff it is the same as, or exactly one step above, their previous degree —
 * i.e. `0 ≤ targetOrdinal − previousOrdinal ≤ 1`. Concretely: BSc → MSc, MSc → MSc
 * or PhD, PhD → PhD.
 *
 * A pure function over the two enums (Admissions' {@see PreviousDegree} and the
 * Catalog\Public {@see DegreeLevel} the seam exposes, ADR-0012), so it is unit-
 * tested directly and reused by both program selection and the finalize guard.
 */
final class DegreeQualification
{
    /** Whether `$previous` qualifies the applicant for a Program at `$target`. */
    public static function qualifies(PreviousDegree $previous, DegreeLevel $target): bool
    {
        $step = self::ordinalOf($target) - self::previousOrdinal($previous);

        return $step >= 0 && $step <= 1;
    }

    /** BSc = 0, MSc = 1, PhD = 2. */
    private static function previousOrdinal(PreviousDegree $degree): int
    {
        return match ($degree) {
            PreviousDegree::BSc => 0,
            PreviousDegree::MSc => 1,
            PreviousDegree::PhD => 2,
        };
    }

    /** On the same scale as {@see self::previousOrdinal()}: MSc = 1, PhD = 2. */
    private static function ordinalOf(DegreeLevel $level): int
    {
        return match ($level) {
            DegreeLevel::MSc => 1,
            DegreeLevel::PhD => 2,
        };
    }
}
