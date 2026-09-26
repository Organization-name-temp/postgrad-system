<?php

namespace Modules\Identity\Public;

use Carbon\CarbonImmutable;

/** An existing Student awaiting the one-time admission Curriculum Version backfill. */
readonly class StudentCurriculumBindingCandidate
{
    public function __construct(
        public int $studentId,
        public int $programId,
        public CarbonImmutable $admittedAt,
    ) {}
}
