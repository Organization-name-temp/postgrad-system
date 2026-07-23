<?php

namespace Modules\Catalog\Internal\Enums;

/**
 * The kind of research a level-700 Research Course is, when it is one (BR-25). PBL
 * courses match an MSc's Research Course slot, Seminar a PhD's; the Phase 3
 * registration classifier reads this to type-match the slot. A closed set in code,
 * in the same style as {@see CourseLevel} and {@see CourseCategory} (ADR-0008).
 *
 * It is NOT coupled to a Course's level — a Course may carry a Research Type or not,
 * regardless of level. A level-700 Course without one simply never matches a
 * Research Course slot. Catalog-owned; stays Internal until a cross-module consumer
 * needs it (the same posture as the sibling Course enums; spec §Implementation
 * Decisions).
 */
enum ResearchType: string
{
    case Pbl = 'PBL';
    case Seminar = 'Seminar';
}
