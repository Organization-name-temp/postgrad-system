<?php

namespace Modules\Enrollment\Public;

interface RemediationObligations
{
    public function createFromRevocation(int $revocationId): RemediationObligationSummary;

    public function reconcileStudent(int $studentId): void;

    /** @return list<RemediationObligationSummary> */
    public function forStudent(int $studentId): array;

    public function releaseByExemption(int $studentId, int $courseId, int $decisionId): void;
}
