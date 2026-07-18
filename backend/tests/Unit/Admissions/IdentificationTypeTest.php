<?php

use Modules\Admissions\Internal\Enums\IdentificationType;

/*
| The Identification Type formats (phase 2.5, ticket 06) — the two identifier
| regexes the enum owns and both the server rule and the client Zod schema use.
| Pure unit coverage of the boundaries the feature tests exercise over HTTP.
*/

it('matches a National ID only when it is exactly 14 digits', function () {
    expect(IdentificationType::NationalId->matches('29001010101010'))->toBeTrue();
    expect(IdentificationType::NationalId->matches('2900101010101'))->toBeFalse();  // 13
    expect(IdentificationType::NationalId->matches('290010101010101'))->toBeFalse(); // 15
    expect(IdentificationType::NationalId->matches('2900101010101A'))->toBeFalse();  // non-digit
});

it('matches a Passport only when it is 6–9 alphanumeric characters', function () {
    expect(IdentificationType::Passport->matches('X1234567'))->toBeTrue();   // 8
    expect(IdentificationType::Passport->matches('ABC123'))->toBeTrue();     // 6
    expect(IdentificationType::Passport->matches('AB123'))->toBeFalse();     // 5
    expect(IdentificationType::Passport->matches('AB12345678'))->toBeFalse(); // 10
    expect(IdentificationType::Passport->matches('AB-12345'))->toBeFalse();  // non-alphanumeric
});
