<?php

namespace Modules\Enrollment\Public;

interface AcademicRequestCloseout
{
    /** Idempotently reject every open Enrollment-owned academic request. */
    public function forTermination(int $studentId, int $terminationCaseId): void;
}
