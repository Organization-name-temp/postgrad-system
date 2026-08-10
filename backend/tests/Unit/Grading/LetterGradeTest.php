<?php

use Modules\Grading\Internal\Enums\LetterGrade;

/*
| The grading scale (ADR-0021), unit-tested directly (spec §Testing Decisions — the pure
| calculator seam, no HTTP stack and no database). The scale is a **static matrix in code**
| (the ADR-0008 house style, as CourseStatus and ApplicationStatus already are), not config,
| precisely so it can be asserted exhaustively here: nine letters, their GPA points, and a
| mapping that is **total over 0–100** — the property SRS Appendix A's non-contiguous bands
| fail, and the reason ADR-0021 replaced them with uniform five-point bands.
|
| Every boundary is asserted from **both sides** (59.99/60, 64.99/65, … 94.99/95), because a
| one-mark slip in a band floor is exactly the bug this table can have and nothing else would
| catch. Marks carry two decimals (the API validates `decimal:0,2`), so x.99 is the real
| largest value below a floor of x+1.
*/

it('ships exactly the nine letters of the scale, and no P/FL or minus grades', function () {
    expect(array_map(fn (LetterGrade $g): string => $g->value, LetterGrade::cases()))
        ->toBe(['A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F']);
});

it('carries the ADR-0021 GPA points for every letter', function () {
    $points = [
        'A+' => 4.00,
        'A' => 4.00,
        'B+' => 3.30,
        'B' => 3.00,
        'C+' => 2.50,
        'C' => 2.00,
        'D+' => 1.30,
        'D' => 1.00,
        'F' => 0.00,
    ];

    foreach ($points as $letter => $expected) {
        expect(LetterGrade::from($letter)->points())->toBe($expected, "{$letter} points");
    }
});

it('carries the inclusive floor of every band', function () {
    $floors = [
        'A+' => 95.0,
        'A' => 90.0,
        'B+' => 85.0,
        'B' => 80.0,
        'C+' => 75.0,
        'C' => 70.0,
        'D+' => 65.0,
        'D' => 60.0,
        'F' => 0.0,
    ];

    foreach ($floors as $letter => $expected) {
        expect(LetterGrade::from($letter)->minimumPercentage())->toBe($expected, "{$letter} floor");
    }
});

it('maps a mark to its letter on both sides of every band boundary', function () {
    // [mark below the floor → letter, the floor itself → letter]
    $boundaries = [
        [59.99, 'F', 60.0, 'D'],
        [64.99, 'D', 65.0, 'D+'],
        [69.99, 'D+', 70.0, 'C'],
        [74.99, 'C', 75.0, 'C+'],
        [79.99, 'C+', 80.0, 'B'],
        [84.99, 'B', 85.0, 'B+'],
        [89.99, 'B+', 90.0, 'A'],
        [94.99, 'A', 95.0, 'A+'],
    ];

    foreach ($boundaries as [$below, $belowLetter, $floor, $floorLetter]) {
        expect(LetterGrade::forMark($below)->value)->toBe($belowLetter, "mark {$below}")
            ->and(LetterGrade::forMark($floor)->value)->toBe($floorLetter, "mark {$floor}");
    }
});

it('maps the two extremes of the range', function () {
    expect(LetterGrade::forMark(0.0)->value)->toBe('F')
        ->and(LetterGrade::forMark(100.0)->value)->toBe('A+');
});

it('maps every mark in 0–100 to some letter, so the scale has no holes', function () {
    // Appendix A's bands left 90–92.99, 80–82.99 and 70–72.99 unrepresentable; a mark of 91
    // could not be graded at all. Walk the whole range in 0.25 steps to prove the
    // replacement is total.
    for ($mark = 0.0; $mark <= 100.0; $mark += 0.25) {
        expect(LetterGrade::forMark($mark))->toBeInstanceOf(LetterGrade::class, "mark {$mark}");
    }

    // The three marks Appendix A could not represent, named explicitly.
    expect(LetterGrade::forMark(91.0)->value)->toBe('A')
        ->and(LetterGrade::forMark(81.0)->value)->toBe('B')
        ->and(LetterGrade::forMark(71.0)->value)->toBe('C');
});

it('places the minimum pass at 75, not Appendix A\'s 77', function () {
    // ADR-0021's most consequential consequence: C+ (2.50 points, Appendix C's
    // min_grade_per_course) now starts at 75. A mark of 76 passes under this scale and
    // failed under the appendix.
    expect(LetterGrade::forMark(75.0)->points())->toBe(2.50)
        ->and(LetterGrade::forMark(76.0)->value)->toBe('C+')
        ->and(LetterGrade::forMark(74.99)->points())->toBe(2.00);
});

it('rejects a mark below zero', function () {
    LetterGrade::forMark(-0.01);
})->throws(InvalidArgumentException::class);

it('rejects a mark above one hundred', function () {
    LetterGrade::forMark(100.01);
})->throws(InvalidArgumentException::class);
