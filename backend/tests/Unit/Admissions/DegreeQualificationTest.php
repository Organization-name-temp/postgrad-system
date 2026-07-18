<?php

use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Admissions\Internal\Qualification\DegreeQualification;
use Modules\Catalog\Public\Enums\DegreeLevel;

/*
| The degree-qualification rule (FR-ADM-004.4): the degrees order BSc < MSc < PhD,
| and an applicant qualifies for a target level iff it is the same as, or exactly one
| step above, their previous degree. A pure unit over the two enums — the one piece
| of program-selection logic worth an authoritative direct test rather than an HTTP
| round-trip (spec §Testing Decisions, lower seam).
*/

it('qualifies a BSc holder for an MSc but not a PhD', function () {
    expect(DegreeQualification::qualifies(PreviousDegree::BSc, DegreeLevel::MSc))->toBeTrue();
    expect(DegreeQualification::qualifies(PreviousDegree::BSc, DegreeLevel::PhD))->toBeFalse();
});

it('qualifies an MSc holder for both an MSc and a PhD', function () {
    expect(DegreeQualification::qualifies(PreviousDegree::MSc, DegreeLevel::MSc))->toBeTrue();
    expect(DegreeQualification::qualifies(PreviousDegree::MSc, DegreeLevel::PhD))->toBeTrue();
});

it('qualifies a PhD holder for a PhD but not a step down to an MSc', function () {
    expect(DegreeQualification::qualifies(PreviousDegree::PhD, DegreeLevel::PhD))->toBeTrue();
    expect(DegreeQualification::qualifies(PreviousDegree::PhD, DegreeLevel::MSc))->toBeFalse();
});
