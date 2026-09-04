<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Models\AutomaticExemptionEvidence;
use Modules\Identity\Public\StudentSummary;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** Prevents registration from outrunning post-commit automatic exemption handling. */
final class AutomaticExemptionReadiness
{
    public function ensureReconciled(StudentSummary $student): void
    {
        if ($student->admissionEvidence === null) {
            return;
        }

        $reconciled = AutomaticExemptionEvidence::query()
            ->where('student_id', $student->studentId)
            ->whereNotNull('reconciled_at')
            ->exists();

        if (! $reconciled) {
            throw new ConflictHttpException(__('credit_decisions.exemptions_pending'));
        }
    }
}
