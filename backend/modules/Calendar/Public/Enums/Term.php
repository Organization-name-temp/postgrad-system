<?php

namespace Modules\Calendar\Public\Enums;

/**
 * The academic term a Semester runs in — Fall or Spring, E-JUST's two main
 * intakes (CONTEXT.md, ADR-0018). Combined with a year it names a Semester and is
 * unique per year. A closed set that lives in code, in the same style as the
 * Catalog DegreeLevel and Identity Role/Permission enums (ADR-0008). Calendar
 * depends on no other domain module, so nothing here references them by type.
 *
 * Unlike Admissions' Internal `IntakeTerm`, this Term lives in `Calendar\Public`:
 * the Semester is consumed by everyone (offerings, registration windows, standing,
 * transcripts — ADR-0018), so the term it carries crosses the module seam. The
 * bilingual labels here derive a Semester's display name without storing it.
 */
enum Term: string
{
    case Fall = 'Fall';
    case Spring = 'Spring';

    /** English label, as written in CONTEXT.md. */
    public function labelEn(): string
    {
        return match ($this) {
            self::Fall => 'Fall',
            self::Spring => 'Spring',
        };
    }

    /** Arabic label (the same rendering the Admissions cycle names use). */
    public function labelAr(): string
    {
        return match ($this) {
            self::Fall => 'خريف',
            self::Spring => 'ربيع',
        };
    }
}
