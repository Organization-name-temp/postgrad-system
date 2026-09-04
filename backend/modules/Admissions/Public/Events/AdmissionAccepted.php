<?php

namespace Modules\Admissions\Public\Events;

use Modules\Admissions\Public\AdmissionAcceptanceEvidence;

/**
 * Published only after the acceptance transaction commits. Consumers receive a
 * historical evidence snapshot and never need to query Admissions internals.
 */
final readonly class AdmissionAccepted
{
    public function __construct(
        public AdmissionAcceptanceEvidence $evidence,
        public string $universityId,
    ) {}
}
