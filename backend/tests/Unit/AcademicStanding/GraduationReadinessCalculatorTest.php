<?php

use Modules\AcademicStanding\Internal\Support\GraduationReadinessCalculator;
use Modules\AcademicStanding\Public\GraduationBlocker;

it('is ready only with complete requirements an available qualifying cgpa and no termination case', function () {
    $result = GraduationReadinessCalculator::calculate(0, 3.0, false);

    expect($result->ready)->toBeTrue()
        ->and($result->blockers)->toBe([])
        ->and($result->approachingGraduation)->toBeFalse();
});

it('returns every applicable typed blocker without treating warning as a blocker', function () {
    $result = GraduationReadinessCalculator::calculate(3, 2.99, true, true);

    expect($result->ready)->toBeFalse()
        ->and($result->approachingGraduation)->toBeTrue()
        ->and($result->blockers)->toBe([
            GraduationBlocker::RemainingRequirements,
            GraduationBlocker::CgpaBelowMinimum,
            GraduationBlocker::TerminationPending,
        ]);
});

it('distinguishes unavailable cgpa and enforces the one-to-five hour approaching boundary', function (int $remaining, bool $complete, bool $approaching) {
    $result = GraduationReadinessCalculator::calculate($remaining, null, false, $complete);

    expect($result->blockers)->toContain(GraduationBlocker::CgpaUnavailable)
        ->and($result->approachingGraduation)->toBe($approaching);
})->with([
    'zero remaining' => [0, true, false],
    'one complete' => [1, true, true],
    'five complete' => [5, true, true],
    'six complete' => [6, true, false],
    'partial schedule' => [3, false, false],
]);
