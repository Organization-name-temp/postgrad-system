<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The semester an Admission Cycle admits into — Fall or Spring, E-JUST's two main
 * intakes (CONTEXT.md). Combined with a year it names a Cycle and is unique per
 * year. A closed set that lives in code, in the same style as the Catalog
 * DegreeLevel and Identity Role/Permission enums (ADR-0008); Admissions-owned,
 * Internal until a cross-module consumer needs it (spec §Implementation Decisions).
 */
enum IntakeTerm: string
{
    case Fall = 'Fall';
    case Spring = 'Spring';
}
