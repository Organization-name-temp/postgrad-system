<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * How strongly a {@see DocumentType} is required of an applicant. A closed,
 * code-owned set (ADR-0008), Admissions-owned and Internal.
 *
 * - {@see self::Required} — always needed for a complete application.
 * - {@see self::Conditional} — needed only when a condition on the applicant's
 *   own snapshot holds (e.g. an English certificate once a test is reported, an
 *   MSc thesis for a PhD applicant). The *static* classification lives here; which
 *   conditional documents are actually owed by a given Application is resolved at
 *   finalize (ticket 06), not by this enum.
 * - {@see self::Optional} — may strengthen an application but is never required.
 */
enum DocumentRequirement: string
{
    case Required = 'required';
    case Conditional = 'conditional';
    case Optional = 'optional';
}
