<?php

namespace Modules\AcademicStanding\Public;

interface TerminationCases
{
    public function currentForStudent(int $studentId): ?TerminationCaseSummary;

    /**
     * Read-only open Cases; absent Students have no current Case.
     *
     * @param  list<int>  $studentIds
     * @return array<int, TerminationCaseSummary>
     */
    public function currentForStudents(array $studentIds): array;

    public function latestForStudent(int $studentId): ?TerminationCaseSummary;

    public function reconcile(int $studentId): ?TerminationCaseSummary;
}
