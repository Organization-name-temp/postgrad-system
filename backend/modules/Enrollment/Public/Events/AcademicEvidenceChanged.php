<?php

namespace Modules\Enrollment\Public\Events;

/** Published after an Enrollment-owned academic fact changes a Student's derived record. */
final readonly class AcademicEvidenceChanged
{
    public function __construct(
        public int $studentId,
        public string $sourceType,
        public int $sourceId,
    ) {}
}
