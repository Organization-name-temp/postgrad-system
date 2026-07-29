<?php

namespace Modules\Grading\Internal\Http\Concerns;

use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;

/**
 * Resolves a Grade Sheet's routing Department and checks a Department Head's authority over it
 * (ADR-0023, ticket 03) — one rule with no special case: the Sheet's Offering's Course's
 * Department, read through `Catalog\Public`, matched against `Identity\Public`'s
 * DepartmentHeadAssignments. Because `Course.department_id` is nullable (ADR-0010 — the shared
 * Preparatory courses), a NULL Course matches the NULL (university-wide) assignment with no
 * branch anywhere in this trait.
 *
 * Shared by the Instructor's sign action (notifying the right Head) and the Department Head's
 * review controller (scoping the queue and the write actions) — the same routing rule read
 * from both directions.
 */
trait ScopesToDepartmentHead
{
    /** The Department id (or null, university-wide) that reviews this Offering's Sheets. */
    private function departmentIdFor(OfferingSummary $offering, CourseCatalog $courses): ?int
    {
        $course = $courses->find($offering->courseId);
        abort_if($course === null, 404, __('grading.offering.missing'));

        return $course->departmentId;
    }

    /**
     * Abort with a 403 unless the acting user is a configured Department Head for `$departmentId`
     * — the same nullable scope {@see self::departmentIdFor()} resolves, so a university-wide
     * Head reaches a department-less Preparatory Sheet and a Department-scoped Head does not.
     */
    private function authorizeDepartmentHead(int $actingUserId, ?int $departmentId, DepartmentHeadAssignments $heads): void
    {
        abort_unless(
            in_array($actingUserId, $heads->headsForDepartment($departmentId), true),
            403,
            __('grading.sheet.not_your_department'),
        );
    }
}
