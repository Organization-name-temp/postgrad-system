<?php

namespace Modules\Enrollment\Internal\Listeners;

use Modules\Enrollment\Public\Events\GradeOutcomesFinalized;
use Modules\Enrollment\Public\MustRetakeObligations;

final readonly class ReconcileMustRetakeObligations
{
    public function __construct(private MustRetakeObligations $obligations) {}

    public function handle(GradeOutcomesFinalized $event): void
    {
        $this->obligations->reconcileEnrollmentIds($event->enrollmentIds);
    }
}
