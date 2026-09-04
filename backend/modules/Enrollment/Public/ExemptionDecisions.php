<?php

namespace Modules\Enrollment\Public;

interface ExemptionDecisions
{
    /** @return list<ExemptionDecisionSummary> */
    public function effectiveForStudent(int $studentId): array;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, list<ExemptionDecisionSummary>>
     */
    public function effectiveForStudents(array $studentIds): array;
}
