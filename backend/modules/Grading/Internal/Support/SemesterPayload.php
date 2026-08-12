<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\SemesterSummary;

/**
 * How Grading names a Semester on the wire: id, term, year and both names — **domain data, never
 * a pre-rendered label**, so the SPA renders it bilingually from the payload exactly as
 * Notifications does.
 *
 * One place, because every Grading surface that mentions a Semester must name it identically: a
 * Dashboard Card and the full record it links into disagreeing about a Semester's shape would be
 * a bug the SPA sees before anyone else.
 */
final class SemesterPayload
{
    /** @return array<string, mixed> */
    public static function from(SemesterSummary $semester): array
    {
        return [
            'id' => $semester->id,
            'term' => $semester->term->value,
            'year' => $semester->year,
            'name_en' => $semester->nameEn,
            'name_ar' => $semester->nameAr,
        ];
    }
}
