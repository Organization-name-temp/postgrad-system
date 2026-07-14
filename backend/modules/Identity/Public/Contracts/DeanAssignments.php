<?php

namespace Modules\Identity\Public\Contracts;

/**
 * The read facade over the Dean↔Faculty routing map (ticket 08). Identity owns the
 * `faculty_dean_assignments` mapping; Admissions reaches it only through this
 * contract (Identity\Public) to scope the Dean review queue — never the Eloquent
 * model, the same sealed seam as {@see AuditLogger}/{@see ApplicantRegistrar}
 * (ADR-0002/0012). Faculties are named by opaque Catalog id (no FK); this facade
 * trades in those ids, never Catalog models.
 *
 * The concrete implementation lives in Identity\Internal and is bound in
 * IdentityServiceProvider. The write side (assign/unassign) is Identity's own admin
 * surface, not part of this cross-module read contract.
 */
interface DeanAssignments
{
    /**
     * The opaque Catalog Faculty ids the given Dean is assigned to — the Faculties
     * whose applications they may review. Empty when the user is not a Dean, or is a
     * Dean with no Faculty yet assigned (so their queue is empty and every action is
     * out-of-faculty, → 403).
     *
     * @return list<int>
     */
    public function facultiesForDean(int $userId): array;

    /**
     * The user ids of the Deans assigned to the given Faculty. Empty when no Dean
     * covers it yet. Symmetric to {@see self::facultiesForDean()} for callers that
     * route the other way (e.g. notifying a Faculty's Deans).
     *
     * @return list<int>
     */
    public function deansForFaculty(int $facultyId): array;
}
