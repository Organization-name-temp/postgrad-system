<?php

namespace Modules\Enrollment\Public;

interface MustRetakeObligations
{
    /** @return list<MustRetakeObligationSummary> */
    public function activeForStudent(int $studentId): array;

    /** @param list<int> $enrollmentIds Reconcile outcomes after a finalized or corrected Grade Sheet. */
    public function reconcileEnrollmentIds(array $enrollmentIds): void;

    /** Close an active obligation because a qualifying Exemption became effective. */
    public function releaseByExemption(int $studentId, int $courseId, int $exemptionDecisionId): void;
}
