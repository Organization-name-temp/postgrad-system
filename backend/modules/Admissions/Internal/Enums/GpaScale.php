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

    /**
     * The largest value a GPA/CGPA on this scale may take — the scale's ceiling
     * (a 4.0-scale GPA is `0 ≤ v ≤ 4.0`, a percentage `0 ≤ v ≤ 100`). Each case's
     * backing value *is* its ceiling by construction, so this reads it directly;
     * it exists as behaviour on the enum (ADR-0008) so the `graduation_gpa` /
     * `graduation_cgpa` rule and its client Zod mirror (ADR-0014) share one source
     * rather than re-deriving the bound. GPA-scale conversion stays out of scope —
     * the value is only *bounded* by its scale, never converted between scales.
     */
    public function ceiling(): float
    {
        return (float) $this->value;
    }

    /**
     * Whether the given GPA/CGPA is valid on this scale — non-negative and no greater
     * than the scale's {@see self::ceiling}. The authoritative check the
     * `graduation_gpa`/`graduation_cgpa` rule calls; the client Zod schema mirrors the
     * same bound (ADR-0014).
     */
    public function allows(float $value): bool
    {
        return $value >= 0.0 && $value <= $this->ceiling();
    }
}
