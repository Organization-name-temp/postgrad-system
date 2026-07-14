<?php

namespace Modules\Identity\Public\Contracts;

/**
 * Creates the User behind a self-registering applicant. Identity owns the User
 * and the Role assignment, so Admissions reaches this concern through this
 * contract (Identity/Public) rather than the User model — the same sanctioned
 * cross-module edge as the acceptance hand-off (ADR-0012/0013). The concrete
 * implementation lives in Identity/Internal and is bound in IdentityServiceProvider.
 */
interface ApplicantRegistrar
{
    /**
     * Create an applicant User — the given name/email with a hashed password and
     * the Applicant role (R01) — and return its id. Runs inside the caller's
     * transaction so that "registering is starting an application" is atomic: the
     * User and the Draft Application commit together or not at all.
     *
     * Email uniqueness is the caller's to validate (and the users unique index to
     * backstop); this method does not authenticate — the caller establishes the
     * session once the surrounding transaction has committed.
     */
    public function register(string $name, string $email, string $plainPassword): int;
}
