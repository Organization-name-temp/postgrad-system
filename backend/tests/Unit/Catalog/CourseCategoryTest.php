<?php

use Modules\Catalog\Internal\Enums\CourseCategory;

/**
 * The three Course Categories and their default credit-hour split are pure domain
 * data straight from the bylaws (BR-14, FR-PCM-002): a Program's required CH per
 * Category defaults to Preparatory 6 / Program Core 6 / Research Elective 9,
 * totalling 21. Tested directly here rather than through an HTTP round-trip
 * because it is authoritative structure, not request-handling behaviour
 * (spec §Testing Decisions, seam 2).
 */
it('has exactly the three fixed course categories, in order', function () {
    expect(CourseCategory::cases())->toBe([
        CourseCategory::Preparatory,
        CourseCategory::ProgramCore,
        CourseCategory::ResearchElective,
    ]);
});

it('defaults each category requirement to the 6/6/9 bylaw split', function () {
    expect(CourseCategory::Preparatory->defaultRequiredCh())->toBe(6)
        ->and(CourseCategory::ProgramCore->defaultRequiredCh())->toBe(6)
        ->and(CourseCategory::ResearchElective->defaultRequiredCh())->toBe(9);
});

it('sums the default per-category requirements to a 21 credit-hour program', function () {
    $total = array_sum(array_map(
        fn (CourseCategory $category): int => $category->defaultRequiredCh(),
        CourseCategory::cases(),
    ));

    expect($total)->toBe(21);
});
