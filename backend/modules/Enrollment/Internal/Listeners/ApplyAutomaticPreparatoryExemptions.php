<?php

namespace Modules\Enrollment\Internal\Listeners;

use Modules\Admissions\Public\Events\AdmissionAccepted;
use Modules\Enrollment\Internal\Support\AutomaticExemptionApplicator;

class ApplyAutomaticPreparatoryExemptions
{
    public function __construct(private readonly AutomaticExemptionApplicator $applicator) {}

    public function handle(AdmissionAccepted $event): void
    {
        // Commit the receipt before evaluation. If the second step fails, acceptance
        // remains durable and the per-Student command can replay this exact snapshot.
        $this->applicator->capture($event->evidence);
        $this->applicator->applyForStudent($event->evidence->studentId);
    }
}
