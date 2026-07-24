<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\CourseStatus;

/**
 * How one {@see CourseStatus} counts in the five accounting dimensions of SRS Appendix D —
 * the row of that table, as an immutable value object. Each flag answers a single yes/no
 * the derived read models of later phases (Credit-Hour Summary, CGPA/CGPA, Academic
 * Standing) read when they tally a Student's Enrollments:
 *
 * - {@see $transcript}  — appears on the transcript at all (every status does).
 * - {@see $cgpa}        — its grade counts toward the CGPA.
 * - {@see $requiredCh}  — counts toward the Program's required credit hours.
 * - {@see $attendedCh}  — counts toward attempted/attended credit hours.
 * - {@see $earnedCh}    — counts toward earned credit hours.
 *
 * The matrix is a plain boolean grid; the Appendix-D qualifiers that a boolean cannot carry
 * — CGPA "latest" vs "both" for a retake, Incomplete's "until resolved", Exempted's
 * "reduces required" — are computation nuances the Phase-4/5 read models layer on top, not
 * flags flipped here. This is the accounting spine those phases build against; Phase 3 only
 * ever reads it for display.
 */
final readonly class CourseStatusCounting
{
    public function __construct(
        public bool $transcript,
        public bool $cgpa,
        public bool $requiredCh,
        public bool $attendedCh,
        public bool $earnedCh,
    ) {}

    /**
     * The array shape for JSON / assertions — the counting row keyed by dimension, so a
     * consumer (and the matrix's unit test) reads it the same way the enum encodes it.
     *
     * @return array{transcript: bool, cgpa: bool, required_ch: bool, attended_ch: bool, earned_ch: bool}
     */
    public function toArray(): array
    {
        return [
            'transcript' => $this->transcript,
            'cgpa' => $this->cgpa,
            'required_ch' => $this->requiredCh,
            'attended_ch' => $this->attendedCh,
            'earned_ch' => $this->earnedCh,
        ];
    }
}
