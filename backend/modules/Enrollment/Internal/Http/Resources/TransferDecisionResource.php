<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\TransferDecision;

/** @mixin TransferDecision */
class TransferDecisionResource extends JsonResource
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
            'normalized_grade' => $this->normalized_grade,
            'verified_by_user_id' => $this->verified_by_user_id,
            'verified_at' => $this->verified_at->toIso8601String(),
            'approved_by_user_id' => $this->approved_by_user_id,
            'approved_at' => $this->approved_at->toIso8601String(),
            'transfer_request_id' => $this->transfer_request_id,
            'effective' => $this->revocation === null,
            'revocation' => $this->revocation === null
                ? null
                : (new CreditDecisionRevocationResource($this->revocation))->resolve(),
        ];
    }
}
