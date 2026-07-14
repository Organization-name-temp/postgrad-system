<?php

namespace Modules\Identity\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;

/**
 * One Dean↔Faculty assignment row for the admin Assignments screen (ticket 08).
 * Carries the row id (so the screen can unassign it), the opaque `faculty_id`, and
 * the assigned Dean's identity — resolved from the eager-loaded `user` relation so
 * the screen can label the row without a second lookup. The Faculty's *name* is not
 * here: it is opaque to Identity (no FK, ADR-0012), so the SPA joins it from the
 * Catalog faculties list it already reads.
 *
 * @mixin FacultyDeanAssignment
 */
class FacultyDeanAssignmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var FacultyDeanAssignment $assignment */
        $assignment = $this->resource;

        return [
            'id' => $assignment->getKey(),
            'user_id' => $assignment->user_id,
            'faculty_id' => $assignment->faculty_id,
            'dean_name' => $assignment->user?->name,
            'dean_email' => $assignment->user?->email,
        ];
    }
}
