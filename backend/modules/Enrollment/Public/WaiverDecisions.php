<?php

namespace Modules\Enrollment\Public;

interface WaiverDecisions
{
    /** @return list<WaiverDecisionSummary> */
    public function effectiveForStudent(int $studentId): array;
}
