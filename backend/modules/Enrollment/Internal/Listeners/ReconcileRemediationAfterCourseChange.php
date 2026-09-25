<?php

namespace Modules\Enrollment\Internal\Listeners;

use Modules\Catalog\Public\Events\CourseChanged;
use Modules\Enrollment\Internal\Support\RemediationReconciler;

final readonly class ReconcileRemediationAfterCourseChange
{
    public function __construct(private RemediationReconciler $remediation) {}

    public function handle(CourseChanged $event): void
    {
        $this->remediation->reconcileCourse($event->courseId);
    }
}
