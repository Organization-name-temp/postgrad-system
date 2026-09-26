<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Models\ExemptionRequestTransition;

/** @mixin ExemptionRequest */
class ExemptionRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'student_id' => $this->student_id,
            'curriculum_version_id' => $this->curriculum_version_id,
            'target_course_id' => $this->target_course_id,
            'target_course_code' => $this->target_course_code,
            'preparatory_requirement_role' => $this->preparatory_requirement_role->value,
            'credit_hours' => $this->credit_hours,
            'evidence' => $this->evidence,
            'student_note' => $this->student_note,
            'status' => $this->status->value,
            'revision' => $this->revision,
            'editable' => $this->status->isEditable(),
            'terminal' => $this->status->isTerminal(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'student' => $this->when($this->getAttribute('student_context') !== null, $this->getAttribute('student_context')),
            'transitions' => $this->whenLoaded('transitions', fn (): array => $this->transitions->map(
                fn (ExemptionRequestTransition $transition): array => [
                    'id' => $transition->getKey(),
                    'from_status' => $transition->from_status?->value,
                    'to_status' => $transition->to_status->value,
                    'action' => $transition->action,
                    'actor_user_id' => $transition->actor_user_id,
                    'reason' => $transition->reason,
                    'revision' => $transition->revision,
                    'evidence_snapshot' => $transition->evidence_snapshot,
                    'current_revision' => $transition->revision === $this->revision,
                    'created_at' => $transition->created_at->toIso8601String(),
                ],
            )->all()),
            'decision' => $this->whenLoaded('decision', fn (): ?array => $this->decision === null
                ? null
                : (new ExemptionDecisionResource($this->decision))->resolve()),
        ];
    }
}
