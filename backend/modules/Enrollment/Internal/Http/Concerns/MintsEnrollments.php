<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Modules\Enrollment\Internal\Http\Controllers\RegistrationController;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationReviewController;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Support\CartLine;
use Modules\Enrollment\Internal\Support\CartViolation;
use Modules\Enrollment\Internal\Support\ExistingEnrollments;
use Modules\Enrollment\Internal\Support\MintPlanEntry;
use Modules\Enrollment\Internal\Support\RetakeDeriver;

/**
 * The Enrollment mint shared by the two surfaces that turn approved cart lines into real
 * Enrollments (ADR-0017): the Advisor's line-item decision
 * ({@see RegistrationReviewController}, ticket 06) and the born-Approved register-for-student
 * compose ({@see RegistrationController}, ticket 09). Both derive each line's Course Status
 * from the Student's record ({@see RetakeDeriver}) and create one snapshotting Enrollment per
 * line, so "how an approved line becomes an Enrollment" is expressed once. Requires the using
 * class to also compose {@see ReadsExistingEnrollments} (for the {@see ExistingEnrollments}
 * this reads against).
 */
trait MintsEnrollments
{
    /**
     * Build the mint plan from resolved cart lines against the Student's record: derive each
     * line's Course Status (Regular / Retake / Audit) with the {@see RetakeDeriver}. A blocked
     * second retake becomes a RETAKE_LIMIT {@see CartViolation} rather than a plan entry, so
     * the caller can 422 it beside the batch violations. The plan preserves each incoming key
     * (request-line id at the advisor decision, cart index at the compose), so a caller can
     * relate a plan entry back to its source line.
     *
     * @param  array<array-key, CartLine>  $cartLines
     * @return array{0: array<array-key, MintPlanEntry>, 1: list<CartViolation>}
     */
    protected function buildMintPlan(array $cartLines, ExistingEnrollments $existing): array
    {
        $deriver = new RetakeDeriver;
        $mintPlan = [];
        $violations = [];

        foreach ($cartLines as $key => $cartLine) {
            $derivation = $deriver->derive($cartLine->mode, $existing->priorForCreditFor($cartLine->courseId));
            if ($derivation->blocked) {
                $violations[] = new CartViolation(
                    CartViolation::RETAKE_LIMIT,
                    "{$cartLine->courseCode} has already used its one allowed retake.",
                    [$cartLine->offeringId],
                );

                continue;
            }
            $mintPlan[$key] = new MintPlanEntry($cartLine, $derivation->status);
        }

        return [$mintPlan, $violations];
    }

    /**
     * Mint one Enrollment per plan entry for the Student, snapshotting its Enrollment
     * Classification, derived Course Status and registration-time credit hours (ADR-0017).
     * The caller runs this inside the decision/compose transaction so the request status and
     * its Enrollments commit together (the ADR-0013 accept→mint precedent).
     *
     * @param  array<array-key, MintPlanEntry>  $mintPlan
     */
    protected function mintEnrollments(int $studentId, array $mintPlan): void
    {
        foreach ($mintPlan as $plan) {
            Enrollment::create([
                'student_id' => $studentId,
                'course_offering_id' => $plan->cartLine->offeringId,
                'classification' => $plan->cartLine->classification,
                'course_status' => $plan->status,
                'credit_hours' => $plan->cartLine->totalCh,
            ]);
        }
    }
}
