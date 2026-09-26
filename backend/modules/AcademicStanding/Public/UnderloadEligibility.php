<?php

namespace Modules\AcademicStanding\Public;

final readonly class UnderloadEligibility
{
    public function __construct(
        public bool $firstSemesterCompletion,
        public bool $approachingGraduationCompletion,
    ) {}

    public function waivesFloor(): bool
    {
        return $this->firstSemesterCompletion || $this->approachingGraduationCompletion;
    }
}
