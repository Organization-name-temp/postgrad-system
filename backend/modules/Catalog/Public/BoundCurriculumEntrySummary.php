<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;

/** One immutable Curriculum Entry read from a specific Program Version snapshot. */
final readonly class BoundCurriculumEntrySummary
{
    public function __construct(
        public int $curriculumVersionId,
        public int $programId,
        public int $courseId,
        public string $courseCode,
        public int $totalCh,
        public CourseCategory $category,
        public ?PreparatoryRequirementRole $preparatoryRequirementRole,
        public int $ordinal,
    ) {}
}
