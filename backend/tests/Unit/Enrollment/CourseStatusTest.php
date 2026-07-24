<?php

use Modules\Enrollment\Internal\Enums\CourseStatus;

/**
 * The CourseStatus counting matrix (SRS Appendix D) — the static grid encoded on the enum
 * (the ADR-0008 pattern), unit-tested directly (spec §Testing Decisions). Every status
 * ships with its row up front; only Regular / Retake / Audit are reachable in Phase 3, but
 * the whole matrix is asserted so a later phase builds on a fixed accounting spine.
 */
it('ships all ten Appendix-D statuses', function () {
    expect(CourseStatus::cases())->toHaveCount(10);
});

it('encodes the Appendix-D counting row for every status', function () {
    // [transcript, cgpa, requiredCh, attendedCh, earnedCh] per SRS Appendix D.
    $matrix = [
        'Regular' => [true, true, true, true, true],
        'Retake' => [true, true, true, true, true],
        'CgpaImprovement' => [true, true, true, true, true],
        'Audit' => [true, false, false, true, false],
        'Incomplete' => [true, false, false, true, false],
        'Transfer' => [true, false, true, false, false],
        'Waive' => [true, false, true, false, false],
        'Withdrawn' => [true, false, false, false, false],
        'FailWithdrawal' => [true, false, false, false, false],
        'Exempted' => [true, false, false, false, false],
    ];

    foreach ($matrix as $case => [$transcript, $cgpa, $requiredCh, $attendedCh, $earnedCh]) {
        $counting = CourseStatus::from($case)->counting();

        expect($counting->transcript)->toBe($transcript, "{$case} transcript")
            ->and($counting->cgpa)->toBe($cgpa, "{$case} cgpa")
            ->and($counting->requiredCh)->toBe($requiredCh, "{$case} requiredCh")
            ->and($counting->attendedCh)->toBe($attendedCh, "{$case} attendedCh")
            ->and($counting->earnedCh)->toBe($earnedCh, "{$case} earnedCh");
    }
});

it('marks every status as appearing on the transcript', function () {
    foreach (CourseStatus::cases() as $status) {
        expect($status->counting()->transcript)->toBeTrue("{$status->value} on transcript");
    }
});

it('counts only Regular and Retake as burning the retake allowance', function () {
    $burns = array_filter(CourseStatus::cases(), fn (CourseStatus $s): bool => $s->burnsRetakeAllowance());

    expect(array_map(fn (CourseStatus $s): string => $s->value, $burns))
        ->toEqualCanonicalizing(['Regular', 'Retake']);
});

it('does not burn the retake allowance for withdrawal or audit', function () {
    expect(CourseStatus::Withdrawn->burnsRetakeAllowance())->toBeFalse()
        ->and(CourseStatus::Audit->burnsRetakeAllowance())->toBeFalse();
});
