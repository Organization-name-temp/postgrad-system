<?php

namespace Modules\Enrollment\Public;

/** Immutable identity of an Exemption decision and its optional revocation. */
final readonly class ExemptionDecisionEvidenceSummary
{
    public function __construct(
        public int $decisionId,
        public int $targetCourseId,
        public ?int $revocationId,
    ) {}
}
