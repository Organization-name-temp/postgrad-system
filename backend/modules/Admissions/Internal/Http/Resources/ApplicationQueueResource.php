<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Http\Resources\Concerns\RendersProgramSummary;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Public\ProgramSummary;

/**
 * A single row of a reviewer queue — the compact view a reviewer scans before opening
 * an application in full. Carries who is applying and for what (name, national id,
 * applying-for degree, resolved primary Program summary), the current status, and the
 * **derived age**: how long the application has waited *in its current status*,
 * computed on demand from the timeline (`occurred_at` of the entry into that status),
 * never stored (spec §SLAs). The full detail + document list come from the
 * {@see ApplicationResource} `show` endpoint.
 *
 * Aging from the *current* status makes this one row serve both queues: the Student
 * Affairs queue (ticket 07, `under_sa_review`) and the faculty-scoped Dean queue
 * (ticket 08, `under_dean_review`) each show days waited since entering their own
 * stage.
 *
 * The primary Program summary is resolved through the `Catalog\Public` facade once
 * for the whole queue and stashed on the model by the controller (`resolved_primary_program`)
 * — so rendering the list is a single facade read, not one per row (ADR-0012). A
 * dropped reference resolves to null.
 *
 * @mixin Application
 */
class ApplicationQueueResource extends JsonResource
{
    use RendersProgramSummary;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Application $application */
        $application = $this->resource;

        $enteredReviewAt = $application->enteredStatusAt($application->status);
        $program = $application->getAttribute('resolved_primary_program');

        return [
            'id' => $application->getKey(),
            'status' => $application->status->value,
            'name_en' => $application->name_en,
            'name_ar' => $application->name_ar,
            'national_id' => $application->national_id,
            'applying_for_degree' => $application->applying_for_degree?->value,
            'primary_program' => $this->programSummaryArray($program instanceof ProgramSummary ? $program : null),
            'finalized_at' => $application->finalized_at?->toIso8601String(),

            // Derived aging: when it entered review, and whole days waited since.
            'review_entered_at' => $enteredReviewAt?->toIso8601String(),
            'age_days' => $enteredReviewAt === null ? null : (int) $enteredReviewAt->diffInDays(now()),
        ];
    }
}
