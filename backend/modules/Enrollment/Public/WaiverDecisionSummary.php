<?php

namespace Modules\Enrollment\Public;

final readonly class WaiverDecisionSummary
{
    public function __construct(
        public int $id,
        public int $requestId,
        public int $studentId,
        public int $curriculumVersionId,
        public int $targetCourseId,
        public string $targetCourseCode,
        public int $creditHours,
        public int $priorCourseId,
        public string $priorCourseCode,
        public int $priorCourseLevel,
        public string $priorResultSource,
        public ?string $priorLetterGrade,
        public float $coveragePercentage,
        public int $verifiedBy,
        public string $verifiedAt,
        public int $approvedBy,
        public string $approvedAt,
    ) {}
}
