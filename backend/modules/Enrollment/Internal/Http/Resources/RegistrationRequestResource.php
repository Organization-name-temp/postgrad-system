<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\RegistrationRequest;

/**
 * A submitted registration request as the Student sees it on the request view (ticket 05,
 * user story 30): its kind and its status in the advisor-review pipeline (Pending /
 * Approved / Rejected), when it was submitted, the Advisor's note (null until a decision —
 * ticket 06 sets it), and its per-line detail.
 *
 * @mixin RegistrationRequest
 */
class RegistrationRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var RegistrationRequest $registration */
        $registration = $this->resource;

        return [
            'id' => $registration->getKey(),
            'semester_id' => $registration->semester_id,
            'kind' => $registration->kind->value,
            'status' => $registration->status->value,
            // The Advisor's note and decision timestamp are set at decision time (ticket 06);
            // both are null while the request is still Pending.
            'advisor_note' => $registration->advisor_note,
            'decided_at' => $registration->decided_at?->toIso8601String(),
            'submitted_at' => $registration->created_at->toIso8601String(),
            'lines' => RegistrationRequestLineResource::collection($registration->lines)->resolve(),
        ];
    }
}
