<?php

namespace Modules\Catalog\Public\Enums;

/**
 * The three roles a Course can play within a Program's Curriculum (ADR-0010,
 * CONTEXT.md). A Category is a property of the placement (Curriculum Entry), never
 * of the Course itself. The set is fixed by the bylaws; each Program stores its
 * required credit hours per Category, defaulting to this {@see self::defaultRequiredCh()}
 * split (6/6/9, total 21 — BR-14, FR-PCM-002).
 *
 * Catalog-owned. It lives in `Catalog\Public` (ticket 03): the Phase-3 Enrollment
 * classifier reads a Curriculum Entry's Category across the module seam to place an
 * eligible course into its Enrollment Classification (ADR-0012). It moved here from
 * `Catalog\Internal` alongside {@see CourseLevel} when that first cross-module
 * consumer arrived.
 */
enum CourseCategory: string
{
    case Preparatory = 'Preparatory';
    case ProgramCore = 'ProgramCore';
    case ResearchElective = 'ResearchElective';

    /**
     * The default required credit hours for this Category when a Program is
     * created without an explicit split — the 6/6/9 bylaw structure (BR-14). A
     * Program may override any of these; this is only the starting point.
     */
    public function defaultRequiredCh(): int
    {
        return match ($this) {
            self::Preparatory => 6,
            self::ProgramCore => 6,
            self::ResearchElective => 9,
        };
    }
}
