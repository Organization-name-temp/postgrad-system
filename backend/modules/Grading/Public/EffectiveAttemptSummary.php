<?php

namespace Modules\Grading\Public;

/** One Course's chronologically latest finalized CGPA-bearing Attempt. */
final readonly class EffectiveAttemptSummary
{
    public function __construct(
        public int $enrollmentId,
        public int $courseId,
        public string $classification,
        public int $creditHours,
        public bool $earned,
        public string $letterGrade,
    ) {}
}
