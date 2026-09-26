<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\ExemptionDecision;

/** @mixin ExemptionDecision */
class ExemptionDecisionResource extends JsonResource
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
            'source' => $this->source->value,
            'reason_codes' => $this->reason_codes,
            'admission_application_id' => $this->admission_application_id,
            'manual_request_id' => $this->manual_request_id,
            'approved_at' => $this->approved_at->toIso8601String(),
            'effective' => $this->revocation === null,
            'revocation' => $this->revocation === null
                ? null
                : (new CreditDecisionRevocationResource($this->revocation))->resolve(),
        ];
    }
}
