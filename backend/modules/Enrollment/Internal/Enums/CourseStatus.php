<?php

namespace Modules\Enrollment\Internal\Enums;

use Modules\Enrollment\Internal\Support\CourseStatusCounting;
use Modules\Enrollment\Internal\Support\RetakeDeriver;

/**
 * The accounting status of one Enrollment — the complete closed set of ten values from
 * SRS Appendix D, in the code-owned static-matrix style of the Identity Role/Permission and
 * Admissions ApplicationStatus enums (ADR-0008). Enrollment-owned and Internal: the value
 * crosses to the SPA as its plain string (the My Schedule StatusChips), so no cross-module
 * consumer names this type.
 *
 * Every value ships up front with its Appendix-D counting row encoded on
 * {@see self::counting()} (the accounting spine Phase 4 grading and Phase 5 summaries read),
 * but only a subset is *reachable* through Phase-3 flows:
 *
 * - **Regular / Retake / Audit** are set at the mint (ticket 06). Retake is always
 *   *derived* from the Student's record ({@see RetakeDeriver}),
 *   never requested.
 * - **Withdrawn** becomes reachable in ticket 07 (self-serve withdrawal → W).
 * - The remaining six — CGPA-Improvement, Transfer, Waive, Fail-Withdrawal, Incomplete,
 *   Exempted — are set by later phases (grades, exemptions/transfers); they exist here so
 *   those phases wire behaviour onto a stable type rather than growing it.
 */
enum CourseStatus: string
{
    /** Taken for credit — counts everywhere (Appendix D row 1). */
    case Regular = 'Regular';

    /**
     * A for-credit re-attempt of a previously-taken course — derived, capped at one per
     * course (FR-REG-006). Counts like Regular; the CGPA "latest vs both" nuance is a
     * Phase-4 read-model detail, not a flag here.
     */
    case Retake = 'Retake';

    /** Sat without credit (AU): on the transcript and attended, but never graded/earned. */
    case Audit = 'Audit';

    /** Self-serve withdrawal (W): on the transcript only; reachable from ticket 07. */
    case Withdrawn = 'Withdrawn';

    /** A grade-improvement re-attempt (Phase 4): counts like Regular, latest grade taken. */
    case CgpaImprovement = 'CgpaImprovement';

    /** Credit transferred in (Phase 5): counts toward required CH only, not the CGPA. */
    case Transfer = 'Transfer';

    /** Requirement waived (Phase 5): counts toward required CH only, not the CGPA. */
    case Waive = 'Waive';

    /** Fail-Withdrawal (FW, Phase 4): on the transcript, counts nowhere else. */
    case FailWithdrawal = 'FailWithdrawal';

    /** Incomplete (I, Phase 4): attended now; CGPA/required/earned pend until resolved. */
    case Incomplete = 'Incomplete';

    /** Exempted (Phase 5): on the transcript; reduces required CH rather than counting. */
    case Exempted = 'Exempted';

    /**
     * This status's Appendix-D counting row — the single static matrix the derived read
     * models tally against (the ADR-0008 pattern, the same static-matrix style as the
     * Admissions ApplicationStatus transition map). Every status appears on the transcript;
     * the other four dimensions vary.
     */
    public function counting(): CourseStatusCounting
    {
        // [transcript, cgpa, requiredCh, attendedCh, earnedCh]
        [$transcript, $cgpa, $requiredCh, $attendedCh, $earnedCh] = match ($this) {
            self::Regular => [true, true, true, true, true],
            self::Retake => [true, true, true, true, true],
            self::CgpaImprovement => [true, true, true, true, true],
            self::Audit => [true, false, false, true, false],
            self::Incomplete => [true, false, false, true, false], // *until resolved
            self::Transfer => [true, false, true, false, false],   // earned CH noted, not counted
            self::Waive => [true, false, true, false, false],      // earned CH noted, not counted
            self::Withdrawn => [true, false, false, false, false],
            self::FailWithdrawal => [true, false, false, false, false],
            self::Exempted => [true, false, false, false, false],  // reduces required CH
        };

        return new CourseStatusCounting($transcript, $cgpa, $requiredCh, $attendedCh, $earnedCh);
    }

    /**
     * Whether an Enrollment in this status is a **gradable line** on a Grade Sheet — one an
     * Instructor is asked for a mark for (ADR-0023, Phase 4). The three for-credit attempt
     * kinds are; nothing else is, and each for a different reason: an **Audit** is sat without
     * credit and a **Withdrawal** left the course, so neither ever earns a letter; a
     * **Transfer**, **Waive** or **Exempted** was decided administratively and was never
     * taught here; a **Fail-Withdrawal** is the terminal outcome of an expired Incomplete; and
     * an **Incomplete** is deferred by definition — it is resolved through its own later Sheet
     * round addressing that one Enrollment, never as a line on the Offering's initial Sheet.
     *
     * Gradability is Enrollment's judgement, read across the seam by Grading through
     * `EnrollmentRecords::gradableEnrollmentsOf()`, so no consumer re-derives it.
     */
    public function isGradable(): bool
    {
        return $this === self::Regular || $this === self::Retake || $this === self::CgpaImprovement;
    }

    /**
     * Whether an Enrollment in this status is a **for-credit attempt** that consumes the
     * single retake allowance (FR-REG-006): a prior Regular or Retake burns it, while a
     * Withdrawn (W) or Audit (AU) attempt does not — those re-register as a fresh Regular.
     * Read by {@see RetakeDeriver}. (CGPA-Improvement
     * routing of passed-course re-registrations is a Phase-4 hook, deliberately unbuilt.)
     */
    public function burnsRetakeAllowance(): bool
    {
        return $this === self::Regular || $this === self::Retake;
    }
}
