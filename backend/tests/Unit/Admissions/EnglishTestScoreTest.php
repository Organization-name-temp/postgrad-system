<?php

use Modules\Admissions\Internal\Enums\EnglishTest;

/*
| The per-test English-score ranges (phase 2.5, ticket 07) — the range table the
| enum owns and both the server `english_score` rule and the client Zod schema
| consume. Pure unit coverage of the boundaries the feature tests exercise over HTTP.
*/

it('maps each real test to its official score range', function () {
    expect(EnglishTest::Ielts->scoreRange())->toBe([0.0, 9.0]);
    expect(EnglishTest::ToeflIbt->scoreRange())->toBe([0.0, 120.0]);
    expect(EnglishTest::ToeflPbt->scoreRange())->toBe([310.0, 677.0]);
    expect(EnglishTest::Pte->scoreRange())->toBe([10.0, 90.0]);
    expect(EnglishTest::CambridgeC1->scoreRange())->toBe([80.0, 230.0]);
    expect(EnglishTest::CambridgeC2->scoreRange())->toBe([80.0, 230.0]);
    expect(EnglishTest::CambridgeB2->scoreRange())->toBe([80.0, 230.0]);
    expect(EnglishTest::Duolingo->scoreRange())->toBe([10.0, 160.0]);
});

it('carries no score range for None', function () {
    expect(EnglishTest::None->scoreRange())->toBeNull();
});

it('accepts a score only within the selected test’s range', function () {
    // IELTS band 0–9: 7.5 is in range, 250 (a TOEFL-shaped number) is not.
    expect(EnglishTest::Ielts->allowsScore(7.5))->toBeTrue();
    expect(EnglishTest::Ielts->allowsScore(0.0))->toBeTrue();   // lower bound
    expect(EnglishTest::Ielts->allowsScore(9.0))->toBeTrue();   // upper bound
    expect(EnglishTest::Ielts->allowsScore(9.5))->toBeFalse();  // above the band
    expect(EnglishTest::Ielts->allowsScore(250.0))->toBeFalse();

    // TOEFL PBT starts at 310 — a value below the floor is rejected.
    expect(EnglishTest::ToeflPbt->allowsScore(300.0))->toBeFalse();
    expect(EnglishTest::ToeflPbt->allowsScore(500.0))->toBeTrue();
});

it('accepts no score for None', function () {
    expect(EnglishTest::None->allowsScore(0.0))->toBeFalse();
    expect(EnglishTest::None->allowsScore(7.5))->toBeFalse();
});
