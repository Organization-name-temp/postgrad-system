<?php

namespace Modules\Enrollment\Public;

interface WaiverDecisions
{
    /** @return list<WaiverDecisionSummary> */
    public function effectiveForStudent(int $studentId): array;

    /** @param list<int> $studentIds
     * @return array<int, list<WaiverDecisionSummary>>
     */
    public function effectiveForStudents(array $studentIds): array;
}
