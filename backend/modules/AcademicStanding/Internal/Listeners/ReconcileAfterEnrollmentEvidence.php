<?php

namespace Modules\AcademicStanding\Internal\Listeners;

use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Internal\Support\AcademicStandingReconciler;
use Modules\Enrollment\Public\Events\AcademicEvidenceChanged;

final readonly class ReconcileAfterEnrollmentEvidence
{
    public function __construct(private AcademicStandingReconciler $reconciler) {}

    public function handle(AcademicEvidenceChanged $event): void
    {
        $canOnlyClearExistingEvidence = in_array($event->sourceType, [
            'automatic_exemption_reconciliation',
            'exemption_decision',
            'transfer_decision',
            'waiver_decision',
        ], true);
        if ($canOnlyClearExistingEvidence && ! TerminationCase::query()->where('student_id', $event->studentId)->whereIn('state', ['AwaitingAdvisor', 'AwaitingDepartmentHead', 'AwaitingCouncil'])->exists()) {
            $this->reconciler->reconcileRegistrationPolicy($event->studentId);

            return;
        }
        $this->reconciler->reconcile($event->studentId, $event->sourceType, $event->sourceId);
    }
}
