<?php

namespace Modules\Identity\Internal\Assignments;

use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;

/**
 * The Eloquent-backed {@see DepartmentHeadAssignments}. Reads the
 * `department_head_assignments` rows and returns plain opaque id lists, so Identity's
 * model never crosses the seam (ADR-0012). Bound to the interface in
 * IdentityServiceProvider.
 */
class EloquentDepartmentHeadAssignments implements DepartmentHeadAssignments
{
    /** @return list<int> */
    public function headsForDepartment(?int $departmentId): array
    {
        // A null $departmentId is the university-wide Preparatory scope, and needs no
        // branch: the query builder turns `where('department_id', null)` into
        // `WHERE department_id IS NULL`, so the Department and university-wide lookups
        // are literally the same line of code (ADR-0023).
        return DepartmentHeadAssignment::query()
            ->where('department_id', $departmentId)
            ->pluck('user_id')
            ->map(static fn (int $id): int => $id)
            ->all();
    }
}
