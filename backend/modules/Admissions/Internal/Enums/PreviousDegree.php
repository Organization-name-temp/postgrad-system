<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The degree an applicant already holds, captured on the Step-1 snapshot. The SRS
 * form (FR-ADM-001) offers BSc/MSc; the spec extends it with PhD so a PhD holder
 * can apply for another PhD. The degree-qualification rule (order BSc < MSc < PhD,
 * enforced at program selection) arrives with its consumer in ticket 06.
 * Admissions-owned, Internal (ADR-0008).
 */
enum PreviousDegree: string
{
    case BSc = 'BSc';
    case MSc = 'MSc';
    case PhD = 'PhD';
}
