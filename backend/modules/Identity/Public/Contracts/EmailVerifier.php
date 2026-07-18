<?php

namespace Modules\Identity\Public\Contracts;

/**
 * Reads and sets the verified state of a User's email — `users.email_verified_at`,
 * which Identity owns. Admissions reaches this concern through this contract
 * (Identity\Public) rather than the User model, the same sanctioned cross-module
 * edge as {@see ApplicantRegistrar}: Admissions drives the wizard's verification
 * step (issuing the signed link, moving the Application on success) but never
 * touches Identity\Internal. The concrete implementation lives in
 * Identity\Internal and is bound in IdentityServiceProvider.
 */
interface EmailVerifier
{
    /**
     * Whether this User's email is already verified. Admissions checks this when
     * the applicant submits Step 1: a pre-verified email shortcuts the Application
     * straight to EmailVerified instead of issuing a fresh link.
     */
    public function isVerified(int $userId): bool;

    /**
     * Mark this User's email verified (stamping `email_verified_at` at the current
     * time). Idempotent: verifying an already-verified email is a no-op, so a
     * re-clicked link is harmless.
     */
    public function markVerified(int $userId): void;
}
