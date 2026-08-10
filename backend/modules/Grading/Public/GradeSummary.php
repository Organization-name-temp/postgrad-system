<?php

namespace Modules\Grading\Public;

use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Grading\Internal\Models\Grade;

/**
 * A read-only view of one Grade for consumers outside Grading (ADR-0012). The Eloquent
 * `Grade` model never leaves `Grading\Internal`, so a consumer gets exactly the mark, the
 * Letter Grade and the GPA points — the same triple {@see Grade::toLineArray()}
 * exposes to the Instructor's own Sheet view.
 *
 * {@see $letterGrade} is the `LetterGrade` enum's plain string value, which stays
 * `Grading\Internal` — the {@see EnrollmentSummary::$classification}
 * precedent, so reading it never drags an Internal type across the seam.
 *
 * Keyed by `enrollment_id` on {@see GradingRecords::gradesFor()} rather than carrying the id
 * itself, since every caller already has the Enrollment in hand and is asking "what was it
 * graded" — Phase 6's transcript composition and Phase 5's standing recompute are the first
 * two consumers. An immutable value object — the facade builds it, callers only read it.
 */
final readonly class GradeSummary
{
    public function __construct(
        public float $mark,
        public string $letterGrade,
        public float $gpaPoints,
    ) {}
}
