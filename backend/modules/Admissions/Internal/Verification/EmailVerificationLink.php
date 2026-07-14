<?php

namespace Modules\Admissions\Internal\Verification;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Modules\Admissions\Internal\Models\Application;

/**
 * Issues the signed, expiring email-verification link for an Application. No
 * mailer is bound in this build (ADR-0005): the link is instead returned in the
 * API response (shown on screen) and written to the log, which is where a
 * developer or demo operator picks it up. The link points at the signed
 * `admissions.verify-email` route and expires after the configured window
 * (default 24h, SRS FR-ADM-002).
 */
class EmailVerificationLink
{
    public function issue(Application $application): string
    {
        $url = URL::temporarySignedRoute(
            'admissions.verify-email',
            now()->addHours((int) config('admissions.email_verification_hours')),
            ['application' => $application->getKey()],
        );

        // The stand-in for the verification email: the link is logged so it can be
        // retrieved without a mailbox.
        Log::info("Admissions: email verification link for application {$application->getKey()} — {$url}");

        return $url;
    }
}
