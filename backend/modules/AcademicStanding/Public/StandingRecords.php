<?php

namespace Modules\AcademicStanding\Public;

interface StandingRecords
{
    public function currentForStudent(int $studentId): StandingSummary;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, StandingSummary>
     */
    public function currentForStudents(array $studentIds): array;

    /** @return list<array<string, mixed>> */
    public function observationsForStudent(int $studentId): array;
}
