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
 * - A **Regular** request depends on how many prior **for-credit** attempts
 *   (Regular / Retake / CGPA Improvement) of the *same course* the Student already has (a
 *   Withdrawal or Audit does **not** count — {@see CourseStatus::burnsRetakeAllowance()}):
 *   - **0** prior for-credit attempts ⇒ a fresh {@see CourseStatus::Regular}.
 *   - **1** prior for-credit attempt ⇒ below C+ becomes {@see CourseStatus::Retake}, at or
 *     above C+ becomes {@see CourseStatus::CgpaImprovement}.
 *   - **2 or more** ⇒ the one re-registration allowance is already used, so minting is
 *     blocked.
 */
final readonly class AttemptDeriver
{
    /**
     * Derive the status (and block flag) for one line.
     *
     * @param  int  $priorForCreditAttempts  count of the Student's existing for-credit
     *                                       (Regular/Retake/CgpaImprovement) Enrollments for
     *                                       this course
     */
    public function derive(
        RegistrationMode $mode,
        int $priorForCreditAttempts,
        ?bool $priorEarned = null,
    ): AttemptDerivation {
        if ($mode === RegistrationMode::Audit) {
            return new AttemptDerivation(CourseStatus::Audit, blocked: false);
        }

        return match (true) {
            $priorForCreditAttempts <= 0 => new AttemptDerivation(CourseStatus::Regular, blocked: false),
            $priorForCreditAttempts === 1 => new AttemptDerivation(
                $priorEarned === true ? CourseStatus::CgpaImprovement : CourseStatus::Retake,
                blocked: false,
            ),
            default => new AttemptDerivation(CourseStatus::Retake, blocked: true),
        };
    }
}
