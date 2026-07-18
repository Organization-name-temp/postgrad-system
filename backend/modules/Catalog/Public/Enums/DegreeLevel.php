<?php

namespace Modules\Catalog\Public\Enums;

use Modules\Catalog\Public\ProgramSummary;

/**
 * The postgraduate degree levels a Program can offer (BR-13). A closed set that
 * lives in code, in the same style as the Identity Role/Permission enums
 * (ADR-0008). Catalog-owned.
 *
 * This enum lives in `Catalog\Public` — the one Catalog type that legitimately
 * crosses the module seam. Admissions reads it to check that an applicant's
 * previous degree qualifies for a chosen Program and that `applying_for_degree`
 * matches the Program's level (FR-ADM-004.4, ADR-0012); it travels on the
 * {@see ProgramSummary} DTO the read facade returns.
 */
enum DegreeLevel: string
{
    case MSc = 'MSc';
    case PhD = 'PhD';
}
