<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Admissions\Internal\Http\Concerns\ResolvesCurrentApplication;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Qualification\DegreeQualification;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramSummary;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Admissions
 *
 * Program selection (ticket 06) — the wizard step after documents. The applicant
 * browses E-JUST's Programs (the SPA reuses the Phase-1 `GET /catalog/tree`) and
 * picks one primary and an optional alternative. Each id is validated to be a real
 * Catalog Program through the {@see ProgramCatalog} read facade — no Eloquent model
 * or foreign key crosses the Admissions ↔ Catalog seam (ADR-0012) — and against the
 * degree-qualification rule (BSc→MSc, MSc→MSc/PhD, PhD→PhD). The chosen ids are
 * stored as opaque references on the applicant's own Application.
 */
class ProgramSelectionController
{
    use ResolvesCurrentApplication;

    /**
     * Choose program(s).
     *
     * Validates and stores the applicant's primary and optional alternative Program.
     * Each id must be a real Program; the primary's degree level must match the
     * `applying_for_degree` from Step 1; and the applicant's previous degree must
     * qualify them for each chosen Program's level. A bad id, a level mismatch, or an
     * unqualified Program is a 422; selecting once the application has left the
     * applicant's hands (finalized/in review) is a 409.
     *
     * @bodyParam primary_program_id integer required The chosen primary Program's id. Example: 1
     * @bodyParam alternative_program_id integer An optional second-choice Program's id; must differ from the primary. Example: 2
     *
     * @response 200 {"data": {"id": 1, "status": "email_verified", "admission_cycle_id": 1, "primary_program_id": 1, "alternative_program_id": 2}}
     * @response 409 scenario="locked" {"message": "This application can no longer be edited."}
     * @response 422 scenario="unknown program" {"message": "The selected program could not be found.", "errors": {"primary_program_id": ["The selected program could not be found."]}}
     * @response 422 scenario="not qualified" {"message": "Your previous degree does not qualify you for this program.", "errors": {"primary_program_id": ["Your previous degree does not qualify you for this program."]}}
     */
    public function update(Request $request, ProgramCatalog $catalog): ApplicationResource
    {
        $application = $this->currentApplication($request);

        if (! $application->isProgramSelectable()) {
            throw new ConflictHttpException(__('admissions.application.not_editable'));
        }

        $validated = $request->validate([
            'primary_program_id' => ['required', 'integer'],
            'alternative_program_id' => ['nullable', 'integer', 'different:primary_program_id'],
        ]);

        $primaryId = (int) $validated['primary_program_id'];
        $alternativeId = isset($validated['alternative_program_id'])
            ? (int) $validated['alternative_program_id']
            : null;

        // One read resolves both references (missing ids are simply absent, ADR-0012).
        $ids = $alternativeId === null ? [$primaryId] : [$primaryId, $alternativeId];
        $summaries = $catalog->findMany($ids);

        $this->assertChoiceValid($application, 'primary_program_id', $summaries[$primaryId] ?? null, matchApplyingDegree: true);

        if ($alternativeId !== null) {
            $this->assertChoiceValid($application, 'alternative_program_id', $summaries[$alternativeId] ?? null, matchApplyingDegree: false);
        }

        $application->update([
            'primary_program_id' => $primaryId,
            'alternative_program_id' => $alternativeId,
        ]);

        return new ApplicationResource($application);
    }

    /**
     * Assert a chosen Program is a valid pick for this applicant, raising a 422 on
     * the given field otherwise: it must exist; the primary must additionally match
     * the applicant's `applying_for_degree`; and the applicant's previous degree must
     * qualify them for the Program's level (FR-ADM-004.4).
     */
    private function assertChoiceValid(
        Application $application,
        string $field,
        ?ProgramSummary $summary,
        bool $matchApplyingDegree,
    ): void {
        if ($summary === null) {
            throw ValidationException::withMessages([$field => [__('admissions.program.not_found')]]);
        }

        if ($matchApplyingDegree && $application->applying_for_degree !== $summary->degreeLevel) {
            throw ValidationException::withMessages([$field => [__('admissions.program.degree_mismatch')]]);
        }

        $previous = $application->previous_degree;
        if (! $previous instanceof PreviousDegree || ! DegreeQualification::qualifies($previous, $summary->degreeLevel)) {
            throw ValidationException::withMessages([$field => [__('admissions.program.not_qualified')]]);
        }
    }
}
