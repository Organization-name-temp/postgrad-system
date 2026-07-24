<?php

namespace Modules\Catalog\Public;

use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Public\Enums\CourseCategory;

/**
 * A read-only view of one Curriculum Entry — the placement of a Course into a Program
 * under a Category — for consumers outside Catalog (ADR-0012). The Eloquent
 * {@see CurriculumEntry} never leaves `Catalog\Internal`; a consumer resolving a
 * Program's Curriculum through {@see CurriculumCatalog} gets exactly these fields:
 * the opaque Program and Course ids, the {@see CourseCategory}, and the manager's
 * within-Category ordinal.
 *
 * Phase-3 Enrollment is the first consumer: the eligibility Classifier reads a
 * candidate Course's Category in the Student's own Curriculum to place it into an
 * Enrollment Classification. An immutable value object.
 */
final readonly class CurriculumEntrySummary
{
    public function __construct(
        public int $programId,
        public int $courseId,
        public CourseCategory $category,
        public int $ordinal,
    ) {}
}
