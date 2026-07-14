<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;

/**
 * A read-only view of a Program for consumers outside Catalog (ADR-0012). This is
 * the *only* shape in which Program data crosses the module seam: the Eloquent
 * {@see Program} model never leaves
 * `Catalog\Internal`, so a consumer gets exactly these fields — id, code, both
 * names, degree level, and the owning Department/Faculty names for display — and
 * none of Catalog's write surface or internal invariants.
 *
 * Admissions is the first consumer (program selection, ticket 06): it validates a
 * chosen Program exists, checks the degree-qualification rule against
 * {@see $degreeLevel}, and renders these summaries on the review and reviewer
 * screens. Ticket 08 adds {@see $facultyId} — the opaque id of the owning Faculty —
 * so the Dean queue can route an application to its Faculty's Dean (resolved
 * `application → program_id → facultyId`, ADR-0012) without a cross-module join.
 * An immutable value object — the facade builds it, callers only read it.
 */
final readonly class ProgramSummary
{
    public function __construct(
        public int $id,
        public string $code,
        public string $nameEn,
        public string $nameAr,
        public DegreeLevel $degreeLevel,
        public int $facultyId,
        public string $departmentNameEn,
        public string $departmentNameAr,
        public string $facultyNameEn,
        public string $facultyNameAr,
    ) {}
}
