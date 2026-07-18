<?php

namespace Modules\Identity\Internal\Assignments;

use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Public\Contracts\DeanAssignments;

/**
 * The Eloquent-backed {@see DeanAssignments}. Reads the `faculty_dean_assignments`
 * rows and returns plain opaque id lists, so Identity's model never crosses the
 * seam (ADR-0012). Bound to the interface in IdentityServiceProvider.
 */
class EloquentDeanAssignments implements DeanAssignments
{
    /** @return list<int> */
    public function facultiesForDean(int $userId): array
    {
        return FacultyDeanAssignment::query()
            ->where('user_id', $userId)
            ->pluck('faculty_id')
            ->map(static fn (int $id): int => $id)
            ->all();
    }

    /** @return list<int> */
    public function deansForFaculty(int $facultyId): array
    {
        return FacultyDeanAssignment::query()
            ->where('faculty_id', $facultyId)
            ->pluck('user_id')
            ->map(static fn (int $id): int => $id)
            ->all();
    }
}
