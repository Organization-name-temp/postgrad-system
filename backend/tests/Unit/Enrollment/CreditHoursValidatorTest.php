<?php

use Modules\Enrollment\Internal\Support\CreditHoursExceptions;
use Modules\Enrollment\Internal\Support\CreditHoursValidator;

/**
 * The pure credit-hour load validator (FR-REG-001), unit-tested directly (the
 * CreditHours/MeetingTime precedent — spec §Testing Decisions). It answers "does this
 * cart's load fall within the min-6 / max-12 window?" — the rule the cart submission and
 * (ticket 06) the advisor decision both enforce. The limits are injected (6/12 here to
 * mirror the config default), so the test never touches config or the database.
 */
$validator = new CreditHoursValidator(minCreditHours: 6, maxCreditHours: 12);

it('passes a load within the floor and ceiling', function () use ($validator) {
    $result = $validator->validate([3, 3, 3], CreditHoursExceptions::none());

    expect($result->passes())->toBeTrue()
        ->and($result->belowMinimum)->toBeFalse()
        ->and($result->aboveMaximum)->toBeFalse();
});

it('passes a load sitting exactly on the floor and on the ceiling', function () use ($validator) {
    expect($validator->validate([6], CreditHoursExceptions::none())->passes())->toBeTrue()
        ->and($validator->validate([6, 6], CreditHoursExceptions::none())->passes())->toBeTrue();
});

it('fails a load below the floor', function () use ($validator) {
    $result = $validator->validate([3], CreditHoursExceptions::none());

    expect($result->belowMinimum)->toBeTrue()
        ->and($result->aboveMaximum)->toBeFalse()
        ->and($result->passes())->toBeFalse();
});

it('fails a load above the ceiling', function () use ($validator) {
    $result = $validator->validate([6, 6, 3], CreditHoursExceptions::none());

    expect($result->aboveMaximum)->toBeTrue()
        ->and($result->belowMinimum)->toBeFalse()
        ->and($result->passes())->toBeFalse();
});

it('counts every regular and audit line toward the load', function () use ($validator) {
    // Four 3-CH lines total 12 — the ceiling, still within limits; a fifth breaks it.
    expect($validator->validate([3, 3, 3, 3], CreditHoursExceptions::none())->aboveMaximum)->toBeFalse()
        ->and($validator->validate([3, 3, 3, 3, 3], CreditHoursExceptions::none())->aboveMaximum)->toBeTrue();
});

it('waives the floor for a first-semester registration', function () use ($validator) {
    $firstSemester = new CreditHoursExceptions(firstSemester: true);
    $result = $validator->validate([3], $firstSemester);

    // A 3-CH first-semester load is allowed — below 6 but the floor is waived.
    expect($result->belowMinimum)->toBeFalse()
        ->and($result->passes())->toBeTrue();
});

it('still enforces the ceiling even with the first-semester waiver', function () use ($validator) {
    $firstSemester = new CreditHoursExceptions(firstSemester: true);
    $result = $validator->validate([6, 6, 3], $firstSemester);

    // The waiver only lifts the floor; a 15-CH first-semester load is still rejected.
    expect($result->aboveMaximum)->toBeTrue()
        ->and($result->passes())->toBeFalse();
});

it('exposes a first-semester exceptions context that waives the floor', function () {
    expect(CreditHoursExceptions::none()->waivesFloor())->toBeFalse()
        ->and((new CreditHoursExceptions(firstSemester: false))->waivesFloor())->toBeFalse()
        ->and((new CreditHoursExceptions(firstSemester: true))->waivesFloor())->toBeTrue();
});
