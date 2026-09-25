<?php

namespace Modules\AcademicStanding\Public;

final readonly class TerminationCaseSummary
{
    /** @param array<string, mixed> $evidence */
    public function __construct(
        public int $id,
        public int $studentId,
        public string $fingerprint,
        public string $state,
        public array $evidence,
        public ?int $advisorUserId = null,
        public ?string $advisorAssessment = null,
        public ?string $advisorRecommendation = null,
        public ?int $departmentHeadUserId = null,
        public ?string $departmentHeadReason = null,
        public ?int $councilUserId = null,
        public ?string $councilReason = null,
        public ?string $closedReason = null,
        public ?string $closedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'student_id' => $this->studentId, 'fingerprint' => $this->fingerprint,
            'state' => $this->state, 'evidence' => $this->evidence,
            'advisor_user_id' => $this->advisorUserId, 'advisor_assessment' => $this->advisorAssessment,
            'advisor_recommendation' => $this->advisorRecommendation,
            'department_head_user_id' => $this->departmentHeadUserId, 'department_head_reason' => $this->departmentHeadReason,
            'council_user_id' => $this->councilUserId, 'council_reason' => $this->councilReason,
            'closed_reason' => $this->closedReason, 'closed_at' => $this->closedAt,
        ];
    }
}
