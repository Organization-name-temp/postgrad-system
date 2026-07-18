<?php

use Modules\Admissions\Internal\Enums\GpaScale;

/*
| The GPA-scale ceilings (phase 2.5, ticket 07) — the per-scale upper bound the
| `graduation_gpa`/`graduation_cgpa` rule enforces and the client Zod schema
| mirrors. Pure unit coverage of the bound the feature tests exercise over HTTP.
*/

it('reports each scale’s ceiling as its numeric value', function () {
    expect(GpaScale::FourPointZero->ceiling())->toBe(4.0);
    expect(GpaScale::FourPointThree->ceiling())->toBe(4.3);
    expect(GpaScale::FivePointZero->ceiling())->toBe(5.0);
    expect(GpaScale::SixPointZero->ceiling())->toBe(6.0);
    expect(GpaScale::SevenPointZero->ceiling())->toBe(7.0);
    expect(GpaScale::TenPointZero->ceiling())->toBe(10.0);
    expect(GpaScale::Percentage->ceiling())->toBe(100.0);
});

it('covers every scale (no case is left without a ceiling)', function () {
    foreach (GpaScale::cases() as $scale) {
        expect($scale->ceiling())->toBeGreaterThan(0.0);
    }
});

it('allows a GPA only when it is within 0 and the scale ceiling', function () {
    // 4.0 scale: 0 and 4.0 are the inclusive bounds; 3.5 is inside; 4.1 and 90 are not.
    expect(GpaScale::FourPointZero->allows(0.0))->toBeTrue();
    expect(GpaScale::FourPointZero->allows(3.5))->toBeTrue();
    expect(GpaScale::FourPointZero->allows(4.0))->toBeTrue();
    expect(GpaScale::FourPointZero->allows(4.1))->toBeFalse();
    expect(GpaScale::FourPointZero->allows(90.0))->toBeFalse();

    // A negative value is never allowed, on any scale.
    expect(GpaScale::Percentage->allows(-1.0))->toBeFalse();

    // The percentage scale admits values up to 100 that a 4.0 scale rejects.
    expect(GpaScale::Percentage->allows(90.0))->toBeTrue();
    expect(GpaScale::Percentage->allows(100.0))->toBeTrue();
    expect(GpaScale::Percentage->allows(101.0))->toBeFalse();
});
