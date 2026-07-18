<?php

namespace Modules\Admissions\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Concerns\ResolvesCurrentApplication;
use Modules\Admissions\Internal\Http\Resources\ApplicationResource;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Verification\EmailVerificationLink;
use Modules\Identity\Public\Contracts\EmailVerifier;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @group Admissions
 *
 * The wizard's email-verification step (ticket 04). Verification reuses a signed,
 * expiring link (no mailer, ADR-0005): clicking it confirms the applicant's email
 * and advances the Application from `submitted` to `email_verified`. A missed link
 * can be re-requested, throttled to a handful per day (BR-72). Verification touches
 * `users.email_verified_at`, which Identity owns, only through the
 * {@see EmailVerifier} Public contract.
 */
class EmailVerificationController
{
    use ResolvesCurrentApplication;

    /**
     * Verify email (signed link).
     *
     * The signed, expiring link the applicant received on submitting Step 1. A
     * valid signature marks their email verified and moves a `submitted`
     * Application to `email_verified`; re-clicking is idempotent. Tampered or
     * expired links are refused (403) by the `signed` middleware.
     *
     * @unauthenticated
     *
     * @urlParam application_id integer required The ID of the application the link verifies. Example: 1
     *
     * @response 200 {"data": {"id": 1, "status": "email_verified"}}
     * @response 403 scenario="bad signature" {"message": "Invalid signature."}
     */
    public function verify(Application $application, EmailVerifier $verifier): JsonResponse
    {
        $verifier->markVerified((int) $application->user_id);

        // Only advance from the awaiting-verification state; a re-clicked link on an
        // already-verified Application is a harmless no-op (idempotent).
        if ($application->status === ApplicationStatus::Submitted) {
            $application->markEmailVerified();
        }

        // This route is unauthenticated — the signature is the only credential, and
        // the link is logged (ADR-0005) — so the response must not echo the
        // personal-info snapshot (national ID, phone, address…). It returns just the
        // id + new status the SPA needs to advance; the applicant reads the full
        // resource through their own authenticated endpoints.
        return response()->json(['data' => [
            'id' => $application->getKey(),
            'status' => $application->status->value,
        ]]);
    }

    /**
     * Resend the verification link.
     *
     * Issues a fresh signed link for the signed-in applicant's Application (in
     * `verification_url`, and logged), for recovering a missed one. Allowed only
     * while the Application is awaiting verification (`submitted`), and throttled to
     * a few attempts per day (BR-72) — the sixth in a day is turned away with 429.
     *
     * @response 200 {"data": {"id": 1, "status": "submitted", "admission_cycle_id": 1, "name_en": "Sara Applicant"}, "verification_url": "http://localhost/api/v1/applications/1/verify-email?expires=1784000000&signature=abc123"}
     * @response 409 scenario="not awaiting verification" {"message": "This application is not awaiting email verification."}
     * @response 429 scenario="throttled" {"message": "Too Many Attempts."}
     */
    public function resend(Request $request, EmailVerificationLink $link): JsonResponse
    {
        $application = $this->currentApplication($request);

        if ($application->status !== ApplicationStatus::Submitted) {
            throw new ConflictHttpException(__('admissions.verification.not_pending'));
        }

        return (new ApplicationResource($application))
            ->additional(['verification_url' => $link->issue($application)])
            ->response();
    }
}
