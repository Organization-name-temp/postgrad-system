<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\GraduationBlocker;
use Modules\AcademicStanding\Public\GraduationReadiness;

final class GraduationReadinessCalculator
{
    public static function calculate(
        int $remainingCreditHours,
        ?float $cgpa,
        bool $terminationPending,
        bool $proposedScheduleCompletesRequirements = false,
        float $minimumCgpa = 3.0,
    ): GraduationReadiness {
        $blockers = [];
        if ($remainingCreditHours > 0) {
            $blockers[] = GraduationBlocker::RemainingRequirements;
        }
        if ($cgpa === null) {
            $blockers[] = GraduationBlocker::CgpaUnavailable;
        } elseif ($cgpa < $minimumCgpa) {
            $blockers[] = GraduationBlocker::CgpaBelowMinimum;
        }
        if ($terminationPending) {
            $blockers[] = GraduationBlocker::TerminationPending;
        }

        return new GraduationReadiness(
            ready: $blockers === [],
            remainingCreditHours: $remainingCreditHours,
            cgpa: $cgpa,
            approachingGraduation: $remainingCreditHours >= 1
                && $remainingCreditHours <= 5
                && $proposedScheduleCompletesRequirements,
            blockers: $blockers,
        );
    }
}
