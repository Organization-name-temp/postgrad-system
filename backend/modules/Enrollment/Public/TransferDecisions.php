<?php

namespace Modules\Enrollment\Public;

interface TransferDecisions
{
    /** @return list<TransferDecisionSummary> */
    public function effectiveForStudent(int $studentId): array;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, list<TransferDecisionSummary>>
     */
    public function effectiveForStudents(array $studentIds): array;
}
