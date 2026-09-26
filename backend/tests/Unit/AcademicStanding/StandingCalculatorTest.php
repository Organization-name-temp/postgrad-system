<?php

use Modules\AcademicStanding\Internal\Support\StandingCalculator;
use Modules\AcademicStanding\Public\AcademicStanding;

it('classifies every standing state in precedence order', function (?float $cgpa, array $earned, bool $terminationPending, AcademicStanding $expected) {
    expect(StandingCalculator::calculate($cgpa, $earned, $terminationPending))->toBe($expected);
})->with([
    'termination precedes no grades' => [null, [], true, AcademicStanding::TerminationPending],
    'no finalized grade' => [null, [], false, AcademicStanding::NotYetAssessed],
    'below 3.00' => [2.99, [true], false, AcademicStanding::AcademicProbation],
    'exactly 3.00 with a failed effective grade' => [3.00, [true, false], false, AcademicStanding::AcademicWarning],
    'different successful Course does not erase failure' => [3.5, [false, true], false, AcademicStanding::AcademicWarning],
    'all effective attempts earned' => [3.0, [true, true], false, AcademicStanding::GoodStanding],
]);
