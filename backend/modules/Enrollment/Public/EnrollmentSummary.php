<?php

namespace Modules\Enrollment\Public;

use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Models\Enrollment;

/**
 * A read-only view of one Enrollment for consumers outside Enrollment (ADR-0012) — a Student
 * taking one Course Offering. The Eloquent {@see Enrollment} model never leaves
 * `Enrollment\Internal`, so a consumer gets exactly these fields: the two ids, the two
 * snapshots taken at the mint (the classification and the credit hours), and the accounting
 * status.
 *
 * {@see $classification} and {@see $courseStatus} are exposed as their enums' plain string
 * values — the `CourseSummary::$researchType` precedent — so reading them never drags
 * {@see EnrollmentClassification} or {@see CourseStatus} across the seam. Those stay
 * `Enrollment\Internal`, which is what lets Enrollment keep its Incomplete bookkeeping private
 * (spec §Modules and seams) rather than promoting the type.
 *
 * {@see $creditHours} is the **snapshot** taken when the Enrollment was minted, not the
 * Course's current total — which is exactly what the credit-weighted GPA must multiply by, so
 * a later Catalog edit never rewrites a past term's arithmetic.
 *
 * An immutable value object — the facade builds it, callers only read it.
 */
final readonly class EnrollmentSummary
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $courseOfferingId,
        public string $classification,
        public string $courseStatus,
        public int $creditHours,
    ) {}
}
