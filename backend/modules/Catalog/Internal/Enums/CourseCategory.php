<?php

namespace Modules\Catalog\Internal\Enums;

/**
 * The three roles a Course can play within a Program's Curriculum (ADR-0010,
 * CONTEXT.md). A Category is a property of the placement (Curriculum Entry), never
 * of the Course itself. The set is fixed by the bylaws; each Program stores its
 * required credit hours per Category, defaulting to this {@see self::defaultRequiredCh()}
 * split (6/6/9, total 21 — BR-14, FR-PCM-002).
 *
 * Catalog-owned and the natural Public candidate for the Phase-3 Enrollment
 * facade, but nothing cross-module consumes it yet, so it stays Internal (spec
 * §Implementation Decisions).
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
