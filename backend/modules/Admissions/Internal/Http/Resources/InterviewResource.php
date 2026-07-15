<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;

/**
 * An {@see Interview} in the `{ data: ... }` envelope — the shared shape the three
 * actors read (ticket 09): the applicant confirming/rescheduling their own, the
 * assigned interviewer working their list and recording an evaluation, and Student
 * Affairs completing it.
 *
 * Carries the booking's confirmation `status` and its single-reschedule budget
 * (`reschedule_count` + `can_reschedule`), the evaluation (score + notes, present once
 * the interviewer records it), the completion stamp, and the embedded
 * {@see InterviewSlotResource} for the when/where/who. The applicant snapshot
 * (who is being interviewed) is included only when the `application` relation is
 * loaded — the reviewer/interviewer screens need it; the applicant's own view does
 * not depend on it.
 *
 * @mixin Interview
 */
class InterviewResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Interview $interview */
        $interview = $this->resource;

        return [
            'id' => $interview->getKey(),
            'application_id' => $interview->application_id,
            'status' => $interview->status->value,
            'reschedule_count' => $interview->reschedule_count,
            'can_reschedule' => $interview->canReschedule(),
            'evaluation_score' => $interview->evaluation_score,
            'evaluation_notes' => $interview->evaluation_notes,
            'completed_at' => $interview->completed_at?->toIso8601String(),
            'slot' => $interview->relationLoaded('slot') && $interview->slot !== null
                ? new InterviewSlotResource($interview->slot)
                : null,
            'applicant' => $this->applicantSnapshot($interview),
        ];
    }

    /**
     * The identity of the applicant being interviewed, for the reviewer/interviewer
     * screens — only when the `application` relation is loaded (the applicant's own
     * view omits it).
     *
     * @return array<string, mixed>|null
     */
    private function applicantSnapshot(Interview $interview): ?array
    {
        if (! $interview->relationLoaded('application') || $interview->application === null) {
            return null;
        }

        /** @var Application $application */
        $application = $interview->application;

        return [
            'name_en' => $application->name_en,
            'name_ar' => $application->name_ar,
            'national_id' => $application->national_id,
            'applying_for_degree' => $application->applying_for_degree?->value,
            'status' => $application->status->value,
        ];
    }
}
