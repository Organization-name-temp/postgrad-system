<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\CreditHourCategory;

final readonly class CreditFact
{
    public function __construct(
        public CreditHourCategory $category,
        public int $courseId,
        public int $creditHours,
    ) {}
}
