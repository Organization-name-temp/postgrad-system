<?php

use Modules\AcademicStanding\Internal\Support\UnderloadRuleCalculator;
use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\ProposedCourse;

function proposed(int $course, int $hours, bool $forCredit = true, CreditHourCategory $category = CreditHourCategory::Preparatory): ProposedCourse
{
    return new ProposedCourse($course, $category, $hours, $forCredit);
}

it('allows only a one-to-five hour first-semester schedule containing every remaining preparatory target', function () {
    expect(UnderloadRuleCalculator::calculate(true, [10, 11], [10, 11], 0, 5, [proposed(10, 2), proposed(11, 3)])->firstSemesterCompletion)->toBeTrue()
        ->and(UnderloadRuleCalculator::calculate(true, [10, 11], [10, 11], 0, 5, [proposed(10, 2)])->firstSemesterCompletion)->toBeFalse()
        ->and(UnderloadRuleCalculator::calculate(true, [], [], 0, 0, [proposed(10, 3)])->firstSemesterCompletion)->toBeFalse()
        ->and(UnderloadRuleCalculator::calculate(true, [10], [10], 0, 3, [proposed(10, 3, false)])->firstSemesterCompletion)->toBeFalse();
});

it('allows an approaching-graduation schedule only when it completes exact targets and category slots', function () {
    $complete = [proposed(10, 2, true, CreditHourCategory::ProgramCore), proposed(20, 3, true, CreditHourCategory::ResearchElective)];

    expect(UnderloadRuleCalculator::calculate(false, [], [10], 3, 5, $complete)->approachingGraduationCompletion)->toBeTrue()
        ->and(UnderloadRuleCalculator::calculate(false, [], [10], 3, 6, $complete)->approachingGraduationCompletion)->toBeFalse()
        ->and(UnderloadRuleCalculator::calculate(false, [], [10, 11], 0, 5, $complete)->approachingGraduationCompletion)->toBeFalse();
});
