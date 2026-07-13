<?php

namespace Modules\Catalog\Internal\Enums;

/**
 * The postgraduate levels a Course can sit at — the leading digit of the E-JUST
 * course code (PRE401 → 400, DEP501 → 500…). A closed set that lives in code, in
 * the same style as DegreeLevel and the Identity Role/Permission enums (ADR-0008).
 * Catalog-owned; it stays Internal until a cross-module consumer needs it (spec
 * §Implementation Decisions).
 */
enum CourseLevel: int
{
    case Foundation = 400;
    case Master = 500;
    case Doctoral = 600;
    case Advanced = 700;
}
