<?php

namespace Modules\AcademicStanding\Public;

interface CreditHourSummaries
{
    public function forStudent(int $studentId): ?CreditHourSummary;

    /** @param list<int> $studentIds
     * @return array<int, CreditHourSummary>
     */
    public function forStudents(array $studentIds): array;
}
