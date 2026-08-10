<?php

namespace Modules\Grading\Internal\Support;

use Carbon\CarbonImmutable;

/**
 * One CGPA-counting, graded Attempt at a Course — the whole input {@see GpaCalculator} needs,
 * and nothing it does not: the Course to group supersession by, the Semester (and its
 * `starts_at`, ADR-0018) to order Attempts and to group a term's GPA by, and the credit hours
 * and GPA points the weighted sum multiplies. Built by {@see StudentGpaTally} from
 * `Enrollment\Public`'s roster and `Calendar\Public`'s Semester dates; the calculator itself
 * touches neither module.
 *
 * `$gpaPoints` is the Grade's own frozen points (ADR-0021) — a plain float, since the
 * calculator converts it to integer hundredths internally to keep the running sum exact
 * (spec §GPA and CGPA's "no floating-point drift" rule).
 */
final readonly class GpaAttempt
{
    public function __construct(
        public int $enrollmentId,
        public int $courseId,
        public int $semesterId,
        public CarbonImmutable $semesterStartsAt,
        public int $creditHours,
        public float $gpaPoints,
    ) {}
}
