<?php

namespace Modules\Catalog\Internal\Enums;

/**
 * The postgraduate degree levels a Program can offer (BR-13). A closed set that
 * lives in code, in the same style as the Identity Role/Permission enums
 * (ADR-0008). Catalog-owned; it stays Internal until a cross-module consumer
 * needs it (spec §Implementation Decisions).
 */
enum DegreeLevel: string
{
    case MSc = 'MSc';
    case PhD = 'PhD';
}
