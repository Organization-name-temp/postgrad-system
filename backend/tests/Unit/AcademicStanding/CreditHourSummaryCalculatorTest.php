<?php

use Modules\AcademicStanding\Internal\Support\CreditFact;
use Modules\AcademicStanding\Internal\Support\CreditHourSummaryCalculator;
use Modules\AcademicStanding\Public\CreditHourCategory;

it('preserves raw facts while allocating decisions before earned credit per target', function () {
    $summary = CreditHourSummaryCalculator::calculate(
        baseline: [
            CreditHourCategory::Preparatory->value => 6,
            CreditHourCategory::ProgramCore->value => 6,
            CreditHourCategory::ResearchElective->value => 9,
        ],
        attended: [
            new CreditFact(CreditHourCategory::Preparatory, 10, 1),
            new CreditFact(CreditHourCategory::Preparatory, 10, 1),
            new CreditFact(CreditHourCategory::ResearchElective, 90, 3),
        ],
        earned: [
            new CreditFact(CreditHourCategory::Preparatory, 10, 1),
            new CreditFact(CreditHourCategory::ProgramCore, 20, 3),
            new CreditFact(CreditHourCategory::ResearchElective, 91, 3),
            new CreditFact(CreditHourCategory::ResearchElective, 92, 9),
        ],
        transferred: [new CreditFact(CreditHourCategory::ProgramCore, 20, 3)],
        waived: [new CreditFact(CreditHourCategory::ResearchElective, 93, 3)],
        exempted: [new CreditFact(CreditHourCategory::Preparatory, 10, 1)],
    );

    expect($summary[CreditHourCategory::Preparatory->value]->toArray())->toMatchArray([
        'baseline_required_ch' => 6,
        'adjusted_required_ch' => 5,
        'attended_ch' => 2,
        'earned_ch' => 1,
        'exempted_ch' => 1,
        'satisfied_ch' => 0,
        'remaining_ch' => 5,
    ])->and($summary[CreditHourCategory::ProgramCore->value]->toArray())->toMatchArray([
        'earned_ch' => 3,
        'transferred_ch' => 3,
        'satisfied_ch' => 3,
        'remaining_ch' => 3,
    ])->and($summary[CreditHourCategory::ResearchElective->value]->toArray())->toMatchArray([
        'earned_ch' => 12,
        'waived_ch' => 3,
        'satisfied_ch' => 9,
        'remaining_ch' => 0,
    ])->and($summary['Total']->toArray())->toMatchArray([
        'baseline_required_ch' => 21,
        'adjusted_required_ch' => 20,
        'attended_ch' => 5,
        'earned_ch' => 16,
        'transferred_ch' => 3,
        'waived_ch' => 3,
        'exempted_ch' => 1,
        'satisfied_ch' => 12,
        'remaining_ch' => 8,
    ]);
});

it('floors requirements and remaining at zero while leaving surplus raw credit visible', function () {
    $summary = CreditHourSummaryCalculator::calculate(
        baseline: [CreditHourCategory::Preparatory->value => 1],
        attended: [],
        earned: [new CreditFact(CreditHourCategory::Preparatory, 1, 4)],
        transferred: [],
        waived: [],
        exempted: [new CreditFact(CreditHourCategory::Preparatory, 2, 3)],
    );

    expect($summary[CreditHourCategory::Preparatory->value]->adjustedRequiredCh)->toBe(0)
        ->and($summary[CreditHourCategory::Preparatory->value]->earnedCh)->toBe(4)
        ->and($summary[CreditHourCategory::Preparatory->value]->satisfiedCh)->toBe(0)
        ->and($summary[CreditHourCategory::Preparatory->value]->remainingCh)->toBe(0);
});
