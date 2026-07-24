<?php

namespace Modules\Catalog\Internal\Enums;

use Modules\Catalog\Public\CourseSummary;

/**
 * The kind of research a level-700 Research Course is, when it is one (BR-25). PBL
 * courses match an MSc's Research Course slot, Seminar a PhD's; the Phase 3
 * registration classifier reads this to type-match the slot. A closed set in code,
 * in the same style as the sibling Course enums (ADR-0008).
 *
 * It is NOT coupled to a Course's level — a Course may carry a Research Type or not,
 * regardless of level. A level-700 Course without one simply never matches a
 * Research Course slot. Catalog-owned; stays Internal even though its sibling enums
 * (CourseLevel, CourseCategory) moved to `Catalog\Public` in ticket 03 — the
 * {@see CourseSummary} facade DTO carries the research type
 * across the seam as its plain string value, so no cross-module consumer references
 * this type directly (spec §Implementation Decisions).
 */
enum ResearchType: string
{
    case Pbl = 'PBL';
    case Seminar = 'Seminar';
}
