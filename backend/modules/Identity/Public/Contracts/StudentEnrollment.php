<?php

namespace Modules\Identity\Public\Contracts;

use Modules\Identity\Public\AdmitStudent;
use Modules\Identity\Public\AdmittedStudent;
use Modules\Identity\Public\UserSummary;

/**
 * The enrolment surface for turning an accepted applicant into a Student — the climax
 * of the admissions pipeline and the first cross-module write into Identity (ADR-0013).
 * It pairs the mint ({@see self::admit()}) with the one read that feeds it — the
 * least-loaded-advisor suggestion the decision form pre-fills ({@see self::suggestAdvisor()})
 * — so everything about *enrolling a student and choosing their advisor* sits behind
 * one contract.
 *
 * Admissions calls {@see self::admit()} synchronously **inside its accept transaction**,
 * so an `Accepted` application and its Student are committed together: there is no
 * Student without an acceptance, and no committed acceptance without a Student.
 *
 * Like every Identity\Public seam it trades in DTOs, never Eloquent models
 * (ADR-0012); the concrete implementation lives in `Identity\Internal` and is bound in
 * IdentityServiceProvider.
 */
interface StudentEnrollment
{
    /**
     * Mint a Student for the accepted applicant, atomically within the caller's
     * transaction: create the `Student` anchor, generate a unique University ID
     * (`{year}-{programCode}-{NNNN}`, a per-(year, program) sequence held under a row
     * lock), **replace** the Applicant role (R01) with Student (R02), assign the
     * advisor, and write an Identity audit entry. Returns the minted identifiers for
     * the caller to surface and to carry on its `AdmissionAccepted` event.
     */
    public function admit(AdmitStudent $command): AdmittedStudent;

    /**
     * The least-loaded academic advisor (R03) — the one with the fewest current
     * advisees, ties broken by ascending user id — to pre-fill on the Dean's decision
     * form. Derived on demand from the live advisee counts, never a stored cursor
     * (spec §Advisor assignment). `null` when no user holds R03. The Dean may override
     * the suggestion to any R03 user; {@see self::admit()} trusts the resolved
     * `advisorId` the caller has already validated.
     */
    public function suggestAdvisor(): ?UserSummary;
}
