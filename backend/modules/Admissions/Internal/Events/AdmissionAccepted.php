<?php

namespace Modules\Admissions\Internal\Events;

use Modules\Admissions\Internal\Listeners\SendAcceptanceNotification;

/**
 * The project's first in-process domain event (ADR-0013): an applicant has been
 * accepted and their Student minted. Dispatched by the final-decision controller
 * **after** the accept-and-mint transaction has committed, so a listener only ever
 * reacts to a real, persisted admission.
 *
 * The event carries side-effects only — it is *not* how the Student is created (that
 * happens synchronously, in-transaction, via `StudentEnrollment::admit`). Its Phase-2
 * listener ({@see SendAcceptanceNotification})
 * sends the acceptance notification; the payload it needs — who, which application, the
 * minted University ID — is all it carries. Later phases hang their own listeners
 * (e.g. exemption evaluation, ADR-0013) off this same event without touching the mint.
 */
final class AdmissionAccepted
{
    public function __construct(
        public int $userId,
        public int $applicationId,
        public string $universityId,
    ) {}
}
