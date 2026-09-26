<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\TransferRequestTransition;

/** @mixin TransferRequest */
class TransferRequestResource extends JsonResource
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
            'target_category' => $this->target_category->value,
            'target_credit_hours' => $this->target_credit_hours,
            'external_institution' => $this->external_institution,
            'external_course_code' => $this->external_course_code,
            'external_course_name' => $this->external_course_name,
            'source_credit_hours' => $this->source_credit_hours,
            'external_grade' => $this->external_grade,
            'document_ids' => $this->document_ids,
            'evidence_documents' => $this->when(
                $this->getAttribute('evidence_documents') !== null,
                $this->getAttribute('evidence_documents'),
            ),
            'student_note' => $this->student_note,
            'normalized_grade' => $this->normalized_grade,
            'verified_by_user_id' => $this->verified_by_user_id,
            'verified_at' => $this->verified_at?->toIso8601String(),
            'status' => $this->status->value,
            'revision' => $this->revision,
            'editable' => $this->status->isEditable(),
            'terminal' => $this->status->isTerminal(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'student' => $this->when($this->getAttribute('student_context') !== null, $this->getAttribute('student_context')),
            'transitions' => $this->whenLoaded('transitions', fn (): array => $this->transitions->map(
                fn (TransferRequestTransition $transition): array => [
                    'id' => $transition->getKey(),
                    'from_status' => $transition->from_status?->value,
                    'to_status' => $transition->to_status->value,
                    'action' => $transition->action,
                    'actor_user_id' => $transition->actor_user_id,
                    'system_cause_type' => $transition->system_cause_type,
                    'system_cause_id' => $transition->system_cause_id,
                    'reason' => $transition->reason,
                    'revision' => $transition->revision,
                    'evidence_snapshot' => $transition->evidence_snapshot,
                    'review_snapshot' => $transition->review_snapshot,
                    'current_revision' => $transition->revision === $this->revision,
                    'created_at' => $transition->created_at->toIso8601String(),
                ],
            )->all()),
            'decision' => $this->whenLoaded('decision', fn (): ?array => $this->decision === null
                ? null
                : (new TransferDecisionResource($this->decision))->resolve()),
        ];
    }
}
