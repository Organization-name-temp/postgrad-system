<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\Catalog\Public\ProgramSummary;
use Modules\Identity\Public\StudentSummary;

final readonly class AcademicCohortSelection
{
    /**
     * @param  array<int, StudentSummary>  $students
     * @param  array<int, ProgramSummary>  $programs
     */
    public function __construct(public array $students, public array $programs) {}
}
