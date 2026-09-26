<?php

namespace Modules\Enrollment\Public;

final readonly class MustRetakeObligationSummary
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $courseId,
        public int $originEnrollmentId,
        public string $category,
        public string $state,
    ) {}
}
