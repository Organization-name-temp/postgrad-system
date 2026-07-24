<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\EnrollmentClassification;

/**
 * A Student's already-minted Enrollment facts, in the shape the mint-time re-validation
 * reads (ticket 06) — so the caps, the duplicate check, the retake derivation and the
 * schedule clash can all be re-checked **against the Student's record**, not just within
 * the request. A pure immutable value object built by the decision controller from the
 * Student's Enrollments; the cart submission (ticket 05) has no record to check against and
 * passes {@see self::none()}.
 *
 * - {@see $enrolledOfferingIds} — every Offering the Student already has an Enrollment for
 *   (any status). An approved line for one of these is a duplicate: since one Offering is
 *   exactly one (course, semester), this is the "one Enrollment per (student, course,
 *   semester)" rule.
 * - {@see $externalElectiveCount} / {@see $researchCourseCount} — how many **active**
 *   Enrollments the Student holds in those classifications, so the composition caps
 *   (external ≤ 1 ever, research ≤ 1) count the record plus the request (BR-24/25).
 * - {@see $priorForCreditCountByCourse} — course id ⇒ count of the Student's **active
 *   for-credit** Enrollments (Regular/Retake) of that course, the input {@see RetakeDeriver}
 *   reads (Withdrawal/Audit attempts are excluded, so they never burn the retake).
 * - {@see $meetings} — the weekly {@see MeetingTime} slots of the Student's active
 *   Enrollments in the **target Semester**, so an approved line that overlaps the existing
 *   schedule is rejected via the shared overlap seam.
 * - {@see $activeCreditHours} — the Student's active credit-hour load already on the
 *   **target Semester**'s schedule, so the credit-hour ceiling counts the whole schedule
 *   (existing + new) for a free add or a late-add, not just the new lines (ticket 07). It
 *   is 0 for an initial cart (nothing minted yet), so the submission/initial-mint paths
 *   are unchanged.
 */
final readonly class ExistingEnrollments
{
    /**
     * @param  list<int>  $enrolledOfferingIds
     * @param  array<int, int>  $priorForCreditCountByCourse
     * @param  list<MeetingTime>  $meetings
     */
    public function __construct(
        public array $enrolledOfferingIds,
        public int $externalElectiveCount,
        public int $researchCourseCount,
        public array $priorForCreditCountByCourse,
        public array $meetings,
        public int $activeCreditHours = 0,
    ) {}

    /** The empty record — nothing minted yet (the cart-submission path, ticket 05). */
    public static function none(): self
    {
        return new self(
            enrolledOfferingIds: [],
            externalElectiveCount: 0,
            researchCourseCount: 0,
            priorForCreditCountByCourse: [],
            meetings: [],
            activeCreditHours: 0,
        );
    }

    /** The Student's existing active count for the given classification (external/research). */
    public function countFor(EnrollmentClassification $classification): int
    {
        return match ($classification) {
            EnrollmentClassification::ExternalElective => $this->externalElectiveCount,
            EnrollmentClassification::ResearchCourse => $this->researchCourseCount,
            default => 0,
        };
    }

    /** How many prior for-credit attempts the Student has of the given course (0 if none). */
    public function priorForCreditFor(int $courseId): int
    {
        return $this->priorForCreditCountByCourse[$courseId] ?? 0;
    }
}
