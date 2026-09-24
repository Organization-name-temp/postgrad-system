<?php

namespace Modules\Enrollment\Public;

final readonly class RemediationObligationSummary
{
    public function __construct(public int $id, public int $studentId, public int $courseId, public int $revocationId, public string $state, public ?int $opportunityOfferingId, public ?int $checkpointSemesterId, public ?string $effectiveDeadline) {}
}
