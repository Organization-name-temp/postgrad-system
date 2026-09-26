<?php

namespace Modules\Enrollment\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\Enrollment\Internal\Models\RemediationObligation;
use Modules\Enrollment\Public\RemediationObligations;

final class ReconcileRemediation extends Command
{
    protected $signature = 'enrollment:reconcile-remediation {student?}';

    protected $description = 'Reconcile post-revocation remediation opportunities and deadlines';

    public function handle(RemediationObligations $obligations): int
    {
        $student = $this->argument('student');
        $ids = $student !== null
            ? [(int) $student]
            : RemediationObligation::query()->whereIn('state', ['AwaitingOffering', 'Active'])->distinct()->pluck('student_id')->map(fn ($id): int => (int) $id)->all();
        foreach ($ids as $studentId) {
            $obligations->reconcileStudent($studentId);
        }

        return self::SUCCESS;
    }
}
