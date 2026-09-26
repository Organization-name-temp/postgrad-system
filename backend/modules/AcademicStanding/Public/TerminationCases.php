<?php

namespace Modules\AcademicStanding\Public;

interface TerminationCases
{
    public function currentForStudent(int $studentId): ?TerminationCaseSummary;

    public function latestForStudent(int $studentId): ?TerminationCaseSummary;

    public function reconcile(int $studentId): ?TerminationCaseSummary;
}
