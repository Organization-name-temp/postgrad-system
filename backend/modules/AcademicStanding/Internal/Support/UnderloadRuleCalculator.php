<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\ProposedCourse;
use Modules\AcademicStanding\Public\UnderloadEligibility;

final class UnderloadRuleCalculator
{
    /**
     * @param  list<int>  $remainingPreparatoryTargets
     * @param  list<int>  $remainingExactTargets
     * @param  list<ProposedCourse>  $proposedCourses
     */
    public static function calculate(
        bool $isEnrollmentSemester,
        array $remainingPreparatoryTargets,
        array $remainingExactTargets,
        int $remainingElectiveCh,
        int $totalRemainingCh,
        array $proposedCourses,
    ): UnderloadEligibility {
        $forCredit = array_values(array_filter($proposedCourses, fn (ProposedCourse $course): bool => $course->forCredit));
        $load = array_sum(array_map(fn (ProposedCourse $course): int => $course->creditHours, $forCredit));
        if ($load < 1 || $load > 5) {
            return new UnderloadEligibility(false, false);
        }

        $firstSemester = $isEnrollmentSemester
            && $remainingPreparatoryTargets !== []
            && self::containsEveryTarget($forCredit, $remainingPreparatoryTargets);
        $electiveCh = array_sum(array_map(
            fn (ProposedCourse $course): int => $course->category === CreditHourCategory::ResearchElective ? $course->creditHours : 0,
            $forCredit,
        ));
        $approaching = $totalRemainingCh >= 1
            && $totalRemainingCh <= 5
            && self::containsEveryTarget($forCredit, $remainingExactTargets)
            && $electiveCh >= $remainingElectiveCh;

        return new UnderloadEligibility($firstSemester, $approaching);
    }

    /**
     * @param  list<ProposedCourse>  $courses
     * @param  list<int>  $targets
     */
    private static function containsEveryTarget(array $courses, array $targets): bool
    {
        $proposed = array_fill_keys(array_map(fn (ProposedCourse $course): int => $course->courseId, $courses), true);
        foreach ($targets as $target) {
            if (! isset($proposed[$target])) {
                return false;
            }
        }

        return true;
    }
}
