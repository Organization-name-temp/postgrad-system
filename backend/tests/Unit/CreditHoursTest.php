<?php

use Modules\Catalog\Internal\Support\CreditHours;

/**
 * The credit-hour breakdown invariant, unit-tested directly (the one piece of pure
 * Catalog arithmetic that earns a test below the HTTP seam — spec §Testing
 * Decisions). A Course's Lecture + Tutorial + Lab must equal its Total
 * (FR-PCM-003.2); the CourseController rejects an unbalanced breakdown with a 422
 * using exactly this predicate.
 */
it('is balanced when lecture + tutorial + lab equals total', function () {
    expect((new CreditHours(lecture: 3, tutorial: 0, lab: 0, total: 3))->isBalanced())->toBeTrue();
    expect((new CreditHours(lecture: 2, tutorial: 1, lab: 1, total: 4))->isBalanced())->toBeTrue();
    expect((new CreditHours(lecture: 0, tutorial: 0, lab: 0, total: 0))->isBalanced())->toBeTrue();
});

it('is unbalanced when the components do not sum to the total', function () {
    expect((new CreditHours(lecture: 3, tutorial: 0, lab: 0, total: 4))->isBalanced())->toBeFalse();
    expect((new CreditHours(lecture: 2, tutorial: 1, lab: 1, total: 3))->isBalanced())->toBeFalse();
    expect((new CreditHours(lecture: 1, tutorial: 1, lab: 1, total: 2))->isBalanced())->toBeFalse();
});

it('exposes the component sum independently of the declared total', function () {
    $ch = new CreditHours(lecture: 3, tutorial: 1, lab: 2, total: 99);

    expect($ch->componentSum())->toBe(6);
});
