<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Illuminate\Http\Request;
use Modules\Admissions\Internal\Http\Controllers\DeanReviewController;
use Modules\Admissions\Internal\Http\Controllers\FinalDecisionController;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\Directory;

/**
 * Dean faculty-scoping — the per-application routing-integrity check shared by the two
 * Dean surfaces, {@see DeanReviewController} (review) and {@see FinalDecisionController}
 * (final decision + resolutions). A Dean may act only on applications whose primary
 * Program sits in one of their assigned Faculties; this keeps that rule in one place so
 * both surfaces enforce it identically.
 *
 * The application's Faculty is resolved `application → primary_program_id → facultyId`
 * through the Catalog\Public {@see ProgramCatalog} facade (ADR-0012), and the Dean's own
 * Faculties through the Identity\Public {@see DeanAssignments} facade. It is checked
 * *before* any state change, so a Dean cannot act on another Faculty's applicant even by
 * guessing an id.
 */
trait ScopesToDeanFaculty
{
    use IdentifiesAdministrator;

    /**
     * Assert the signed-in Dean may act on this application — its primary Program's
     * Faculty is among the Dean's assigned Faculties. A dropped/absent Program reference
     * (no FK, ADR-0012) or an out-of-faculty Program is refused with a 403.
     *
     * An administrator (R09/R10) bypasses the faculty scope entirely (ADR-0015): they
     * oversee every Faculty through this same surface, so they are never refused here.
     */
    protected function authorizeDeanFacultyScope(
        Request $request,
        Application $application,
        DeanAssignments $assignments,
        ProgramCatalog $catalog,
        Directory $directory,
    ): void {
        if ($this->actingAsAdministrator($request, $directory)) {
            return;
        }

        $programId = $application->primary_program_id;
        $summary = $programId === null ? null : $catalog->find($programId);
        $facultyIds = $assignments->facultiesForDean((int) $request->user()?->getAuthIdentifier());

        if ($summary === null || ! in_array($summary->facultyId, $facultyIds, true)) {
            abort(403);
        }
    }
}
