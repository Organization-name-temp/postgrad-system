<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\RegistrationMode;

/**
 * Derives the {@see CourseStatus} of a newly-minted Enrollment from the Student's record —
 * the pure support seam the advisor mint reads (ticket 06, spec §Rules engines,
 * FR-REG-006). A Student never *chooses* Retake; it is derived here, so the rule lives in
 * one unit-testable place with no HTTP stack or database:
 *
 * - An **Audit** request is always {@see CourseStatus::Audit} — auditing is never a retake,
 *   and never blocked by prior attempts.
 * - A **Regular** request depends on how many prior **for-credit** attempts (Regular /
 *   Retake) of the *same course* the Student already has (a Withdrawal or Audit does **not**
 *   count — {@see CourseStatus::burnsRetakeAllowance()}):
 *   - **0** prior for-credit attempts ⇒ a fresh {@see CourseStatus::Regular}.
 *   - **1** prior for-credit attempt ⇒ {@see CourseStatus::Retake} (the single allowed retake).
 *   - **2 or more** ⇒ still Retake by nature, but **blocked** — a second retake is refused.
 *
 * Grade-gated routing (below-C+ retake gates, passed-course re-registration → CGPA
 * Improvement, the failed-external cap-freeing rule) are documented Phase-4 hooks,
 * deliberately unbuilt (spec §Out of Scope).
 */
final readonly class RetakeDeriver
{
    /**
     * Derive the status (and block flag) for one line.
     *
     * @param  int  $priorForCreditAttempts  count of the Student's existing for-credit
     *                                       (Regular/Retake) Enrollments for this course
     */
    public function derive(RegistrationMode $mode, int $priorForCreditAttempts): RetakeDerivation
    {
        if ($mode === RegistrationMode::Audit) {
            return new RetakeDerivation(CourseStatus::Audit, blocked: false);
        }

        return match (true) {
            $priorForCreditAttempts <= 0 => new RetakeDerivation(CourseStatus::Regular, blocked: false),
            $priorForCreditAttempts === 1 => new RetakeDerivation(CourseStatus::Retake, blocked: false),
            default => new RetakeDerivation(CourseStatus::Retake, blocked: true),
        };
    }
}
