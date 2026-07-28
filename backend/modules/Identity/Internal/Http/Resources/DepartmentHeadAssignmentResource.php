<?php

namespace Modules\Identity\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;

/**
 * One Department-Head↔Department assignment row for the admin Assignments area
 * (phase 4, ticket 02). Carries the row id (so the screen can unassign it), the
 * opaque `department_id`, and the assigned Head's identity — resolved from the
 * eager-loaded `user` relation so the screen can label the row without a second
 * lookup. The Department's *name* is not here: it is opaque to Identity (no FK,
 * ADR-0012), so the SPA joins it from the Catalog departments list it already reads.
 *
 * A null `department_id` is the university-wide Preparatory scope, not a blank cell
 * (ADR-0023) — so the payload says so in a named `university_wide` flag rather than
 * leaving every reader, human or client, to infer meaning from an absent value. It
 * is derived, not stored; the column stays the single source of truth.
 *
 * @mixin DepartmentHeadAssignment
 */
class DepartmentHeadAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var DepartmentHeadAssignment $assignment */
        $assignment = $this->resource;

        return [
            'id' => $assignment->getKey(),
            'user_id' => $assignment->user_id,
            'department_id' => $assignment->department_id,
            'university_wide' => $assignment->department_id === null,
            'head_name' => $assignment->user?->name,
            'head_email' => $assignment->user?->email,
        ];
    }
}
