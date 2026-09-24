<?php

namespace Modules\AcademicStanding\Public;

interface StandingRecords
{
    public function currentForStudent(int $studentId): StandingSummary;

    /** @return list<array<string, mixed>> */
    public function observationsForStudent(int $studentId): array;
}
