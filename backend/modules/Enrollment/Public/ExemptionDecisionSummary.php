<?php

namespace Modules\Enrollment\Public;

use Illuminate\Support\Carbon;

/** Immutable effective Exemption fact crossing the Enrollment public seam. */
final readonly class ExemptionDecisionSummary
{
    /** @param list<string> $reasonCodes */
    public function __construct(
        public int $id,
        public int $studentId,
        public int $curriculumVersionId,
        public int $targetCourseId,
        public string $targetCourseCode,
        public string $preparatoryRequirementRole,
        public int $creditHours,
        public string $source,
        public array $reasonCodes,
        public Carbon $approvedAt,
    ) {}
}
