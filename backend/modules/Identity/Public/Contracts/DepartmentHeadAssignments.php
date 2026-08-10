<?php

namespace Modules\Identity\Public\Contracts;

/**
 * The read facade over the Department-Head↔Department routing map (phase 4, ticket
 * 02). Identity owns the `department_head_assignments` mapping; Grading reaches it
 * only through this contract (Identity\Public) to route a signed Grade Sheet to its
 * reviewer — never the Eloquent model, the same sealed seam {@see DeanAssignments}
 * sits behind (ADR-0002/0012). Departments are named by opaque Catalog id (no FK);
 * this facade trades in those ids, never Catalog models.
 *
 * The concrete implementation lives in Identity\Internal and is bound in
 * IdentityServiceProvider. The write side (assign/unassign) is Identity's own admin
 * surface, not part of this cross-module read contract.
 */
interface DepartmentHeadAssignments
{
    /**
     * The user ids of the Department Heads who review the given Department's Grade
     * Sheets. Empty when no Head covers it yet — the state the admin screen surfaces
     * so grades do not strand in review.
     *
     * `$departmentId` is **nullable**, and null is a scope rather than a missing
     * argument: it is the university-wide Preparatory scope that PRE401–404 fall
     * under, because their Courses belong to no Department (ADR-0010, ADR-0023).
     * That symmetry is what lets a caller route every Sheet through this one call —
     * `headsForDepartment($course->departmentId)` — with no special case.
     *
     * The two scopes are disjoint: a university-wide Head does not stand in for a
     * Department that has none, and a Department's Head does not review Preparatory
     * Sheets.
     *
     * @return list<int>
     */
    public function headsForDepartment(?int $departmentId): array;
}
