<?php

use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Support\RetakeDeriver;

/**
 * Retake derivation (FR-REG-006), unit-tested directly (spec §Testing Decisions). A Student
 * never chooses Retake; the deriver reads how many prior *for-credit* attempts of a course
 * the Student holds and returns the status the minted Enrollment carries — Regular, Retake,
 * or blocked (a second retake). Withdrawal/Audit attempts don't count toward the prior
 * total, so re-registering after them is a fresh Regular (that filtering is the caller's;
 * here the count is the input).
 */
$deriver = new RetakeDeriver;

it('mints a fresh Regular with no prior for-credit attempts', function () use ($deriver) {
    $result = $deriver->derive(RegistrationMode::Regular, priorForCreditAttempts: 0);

    expect($result->status)->toBe(CourseStatus::Regular)
        ->and($result->blocked)->toBeFalse();
});

it('derives a Retake when one prior for-credit attempt exists', function () use ($deriver) {
    $result = $deriver->derive(RegistrationMode::Regular, priorForCreditAttempts: 1);

    expect($result->status)->toBe(CourseStatus::Retake)
        ->and($result->blocked)->toBeFalse();
});

it('blocks a second retake (two prior for-credit attempts)', function () use ($deriver) {
    $result = $deriver->derive(RegistrationMode::Regular, priorForCreditAttempts: 2);

    expect($result->status)->toBe(CourseStatus::Retake)
        ->and($result->blocked)->toBeTrue();
});

it('always mints Audit for an audit request, regardless of priors', function () use ($deriver) {
    foreach ([0, 1, 2, 5] as $priors) {
        $result = $deriver->derive(RegistrationMode::Audit, priorForCreditAttempts: $priors);

        expect($result->status)->toBe(CourseStatus::Audit)
            ->and($result->blocked)->toBeFalse();
    }
});

it('treats zero-or-negative priors as a fresh Regular (defensive)', function () use ($deriver) {
    expect($deriver->derive(RegistrationMode::Regular, -1)->status)->toBe(CourseStatus::Regular);
});
