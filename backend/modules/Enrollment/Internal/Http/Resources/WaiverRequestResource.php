<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Internal\Models\WaiverRequestTransition;

/** @mixin WaiverRequest */
class WaiverRequestResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $verification = $this->verified_by === null ? null : [
            'coverage_percentage' => $this->coverage_percentage,
            'equivalency_note' => $this->equivalency_note,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];

        return [
            'id' => $this->getKey(),
            'student_id' => $this->student_id,
            'curriculum_version_id' => $this->curriculum_version_id,
            'target_course_id' => $this->target_course_id,
            'target_course_code' => $this->target_course_code,
            'credit_hours' => $this->credit_hours,
            'prior_course_id' => $this->prior_course_id,
            'prior_course_code' => $this->prior_course_code,
            'prior_course_level' => $this->prior_course_level,
            'prior_result_source' => $this->prior_result_source,
            'prior_enrollment_id' => $this->prior_enrollment_id,
            'prior_letter_grade' => $this->prior_letter_grade,
            'evidence' => $this->evidence,
            'evidence_documents' => $this->when(
                $this->getAttribute('evidence_documents') !== null,
                $this->getAttribute('evidence_documents'),
            ),
            'student_note' => $this->student_note,
            'status' => $this->status->value,
            'revision' => $this->revision,
            'editable' => $this->status->isEditable(),
            'terminal' => $this->status->isTerminal(),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'head_verification' => $verification,
            'student' => $this->when($this->getAttribute('student_context') !== null, $this->getAttribute('student_context')),
            'transitions' => $this->whenLoaded('transitions', fn (): array => $this->transitions->map(
                fn (WaiverRequestTransition $transition): array => [
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
                    'verification_snapshot' => $transition->verification_snapshot,
                    'current_revision' => $transition->revision === $this->revision,
                    'created_at' => $transition->created_at->toIso8601String(),
                ],
            )->all()),
            'decision' => $this->whenLoaded('decision', fn (): ?array => $this->decision === null
                ? null
                : (new WaiverDecisionResource($this->decision))->resolve()),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
