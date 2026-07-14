<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;

/**
 * One row of an Application's status timeline in the { data: ... } envelope (ticket
 * 07). Read-only over the append-only transition log: the state the application left
 * (`from_status`, null for an initial row), the state it entered, the reviewer's note
 * (return instructions / decision reason, when present), and when. The SPA renders
 * each status with its colocated `statusToChip` map, so no label is sent here.
 *
 * The actor is surfaced as an opaque `actor_user_id` (or null for an automatic step)
 * — the log names users by id, and the timeline never resolves it to a name.
 *
 * @mixin ApplicationStatusTransition
 */
class ApplicationStatusTransitionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ApplicationStatusTransition $transition */
        $transition = $this->resource;

        return [
            'id' => $transition->getKey(),
            'from_status' => $transition->from_status?->value,
            'to_status' => $transition->to_status->value,
            'actor_user_id' => $transition->actor_user_id,
            'note' => $transition->note,
            'occurred_at' => $transition->occurred_at->toIso8601String(),
        ];
    }
}
