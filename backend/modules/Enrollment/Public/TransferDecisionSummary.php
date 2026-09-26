<?php

namespace Modules\Enrollment\Public;

use Illuminate\Support\Carbon;

/** Immutable effective Transfer fact crossing the Enrollment public seam. */
final readonly class TransferDecisionSummary
{
    /** @param list<string> $documentIds */
    public function __construct(
        public int $id,
        public int $studentId,
        public int $curriculumVersionId,
        public int $targetCourseId,
        public string $targetCourseCode,
        public string $targetCategory,
        public int $targetCreditHours,
        public string $externalInstitution,
        public string $externalCourseCode,
        public string $externalCourseName,
        public string $sourceCreditHours,
        public string $externalGrade,
        public array $documentIds,
        public string $normalizedGrade,
        public int $verifiedByUserId,
        public Carbon $verifiedAt,
        public int $approvedByUserId,
        public Carbon $approvedAt,
    ) {}
}
