<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The scale an applicant's graduation GPA/CGPA is expressed on (Step-1 snapshot,
 * SRS §4.2). The supported values come from the MVP UML supported-values list
 * (4.0, 4.3, 5.0, 6.0, 7.0, 10.0, and 100 = percentage). GPA-scale/percentage
 * conversion is explicitly out of scope for this build (spec §Out of Scope) — the
 * scale and the raw values are only *captured*, not converted. Admissions-owned,
 * Internal (ADR-0008).
 */
enum GpaScale: string
{
    case FourPointZero = '4.0';
    case FourPointThree = '4.3';
    case FivePointZero = '5.0';
    case SixPointZero = '6.0';
    case SevenPointZero = '7.0';
    case TenPointZero = '10.0';
    case Percentage = '100';
}
