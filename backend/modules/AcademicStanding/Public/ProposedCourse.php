<?php

namespace Modules\AcademicStanding\Public;

/** One course on a proposed Semester schedule. */
final readonly class ProposedCourse
{
    public function __construct(
        public int $courseId,
        public CreditHourCategory $category,
        public int $creditHours,
        public bool $forCredit,
    ) {}
}
