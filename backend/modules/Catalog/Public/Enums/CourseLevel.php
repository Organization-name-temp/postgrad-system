<?php

namespace Modules\Catalog\Public\Enums;

use Modules\Catalog\Public\CourseSummary;

/**
 * The postgraduate levels a Course can sit at — the leading digit of the E-JUST
 * course code (PRE401 → 400, DEP501 → 500…). A closed set that lives in code, in
 * the same style as DegreeLevel and the Identity Role/Permission enums (ADR-0008).
 * Catalog-owned.
 *
 * It lives in `Catalog\Public` (ticket 03): the Phase-3 {@see CourseSummary}
 * facade DTO carries a Course's level across the module seam, so Enrollment reads it
 * when classifying an eligible course by degree level (ADR-0012). It moved here from
 * `Catalog\Internal` the moment that first cross-module consumer arrived.
 */
enum CourseLevel: int
{
    case Foundation = 400;
    case Master = 500;
    case Doctoral = 600;
    case Advanced = 700;
}
