<?php

namespace Modules\Enrollment\Internal\Listeners;

use Modules\Calendar\Public\Events\SemesterChanged;
use Modules\Enrollment\Internal\Support\RemediationReconciler;

final readonly class ReconcileRemediationAfterSemesterChange
{
    public function __construct(private RemediationReconciler $remediation) {}

    public function handle(SemesterChanged $event): void
    {
        $this->remediation->reconcileSemester($event->semesterId);
    }
}
