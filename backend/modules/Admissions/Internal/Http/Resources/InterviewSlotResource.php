<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Http\Concerns\ResolvesInterviewers;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Public\UserSummary;

/**
 * An {@see InterviewSlot} in the `{ data: ... }` envelope — the row Student Affairs
 * scans when managing the pool and scheduling, and the applicant sees for their
 * booked/reschedulable slots. Carries the time, duration, mode and its matching
 * coordinate (in-person `location` or virtual `meeting_link`), the resolved
 * interviewer's display identity, and whether the slot is still open.
 *
 * The interviewer is a faculty User referenced by opaque id (no FK, ADR-0012),
 * resolved through the Identity\Public directory facade and stashed on the model as
 * `resolved_interviewer` by the controller (see {@see ResolvesInterviewers})
 * — so a list of slots costs one directory read, not one per row. `is_open` is derived
 * from whether an interview is booked into the slot (the `interview` relation), never
 * a stored flag.
 *
 * @mixin InterviewSlot
 */
class InterviewSlotResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var InterviewSlot $slot */
        $slot = $this->resource;

        $interviewer = $slot->getAttribute('resolved_interviewer');

        return [
            'id' => $slot->getKey(),
            'scheduled_at' => $slot->scheduled_at->toIso8601String(),
            'duration_minutes' => $slot->duration_minutes,
            'mode' => $slot->mode->value,
            'location' => $slot->location,
            'meeting_link' => $slot->meeting_link,
            'interviewer_id' => $slot->interviewer_id,
            'interviewer' => $interviewer instanceof UserSummary ? $interviewer->toArray() : null,
            'is_open' => $this->isSlotOpen($slot),
        ];
    }

    /**
     * Whether the slot is open. Prefers the eager-loaded `interview` relation (so a
     * list rendered with `with('interview')` costs no extra query per row) and falls
     * back to a direct existence check when it was not loaded.
     */
    private function isSlotOpen(InterviewSlot $slot): bool
    {
        if ($slot->relationLoaded('interview')) {
            return $slot->interview === null;
        }

        return $slot->isOpen();
    }
}
