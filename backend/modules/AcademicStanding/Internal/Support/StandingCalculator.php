<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\AcademicStanding;

final class StandingCalculator
{
    /** @param list<bool> $effectiveEarned */
    public static function calculate(?float $cgpa, array $effectiveEarned, bool $terminationPending): AcademicStanding
    {
        if ($terminationPending) {
            return AcademicStanding::TerminationPending;
        }
        if ($cgpa === null) {
            return AcademicStanding::NotYetAssessed;
        }
        if ($cgpa < 3.0) {
            return AcademicStanding::AcademicProbation;
        }
        if (in_array(false, $effectiveEarned, true)) {
            return AcademicStanding::AcademicWarning;
        }

        return AcademicStanding::GoodStanding;
    }
}
