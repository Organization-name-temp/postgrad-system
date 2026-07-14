<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The applicant's gender as captured on the Step-1 personal-info snapshot
 * (SRS §4.2 FR-ADM-001) — Male or Female, the two values the intake form offers.
 * A closed set that lives in code, in the same style as the Identity Role and
 * Admissions IntakeTerm enums (ADR-0008); Admissions-owned and Internal.
 */
enum Gender: string
{
    case Male = 'Male';
    case Female = 'Female';
}
