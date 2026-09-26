<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\Public\Enums\CourseCategory;

/** The required credit-hour split frozen into one bound Curriculum Version. */
final readonly class CurriculumRequirementsSummary
{
    public function __construct(
        public int $curriculumVersionId,
        public int $programId,
        public int $preparatoryCh,
        public int $programCoreCh,
        public int $researchElectiveCh,
    ) {}

    public function for(CourseCategory $category): int
    {
        return match ($category) {
            CourseCategory::Preparatory => $this->preparatoryCh,
            CourseCategory::ProgramCore => $this->programCoreCh,
            CourseCategory::ResearchElective => $this->researchElectiveCh,
        };
    }
}
