<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Enrollment\Internal\Models\WaiverDecision;

/** @mixin WaiverDecision */
class WaiverDecisionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'waiver_request_id' => $this->waiver_request_id,
            'student_id' => $this->student_id,
            'curriculum_version_id' => $this->curriculum_version_id,
            'target_course_id' => $this->target_course_id,
            'target_course_code' => $this->target_course_code,
            'credit_hours' => $this->credit_hours,
            'prior_course_id' => $this->prior_course_id,
            'prior_course_code' => $this->prior_course_code,
            'prior_course_level' => $this->prior_course_level,
            'prior_result_source' => $this->prior_result_source,
            'prior_letter_grade' => $this->prior_letter_grade,
            'coverage_percentage' => $this->coverage_percentage,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at->toIso8601String(),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at->toIso8601String(),
            'effective' => $this->revocation === null,
            'revocation' => $this->revocation === null
                ? null
                : (new CreditDecisionRevocationResource($this->revocation))->resolve(),
        ];
    }
}
