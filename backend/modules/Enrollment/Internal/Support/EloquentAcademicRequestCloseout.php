<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Public\AcademicRequestCloseout;

final class EloquentAcademicRequestCloseout implements AcademicRequestCloseout
{
    public function forTermination(int $studentId, int $terminationCaseId): void
    {
        RegistrationRequest::query()->where('student_id', $studentId)->whereIn('status', [RegistrationStatus::Pending, RegistrationStatus::AwaitingCapacityOverride])
            ->update(['status' => RegistrationStatus::Rejected, 'advisor_note' => 'System rejected by termination case #'.$terminationCaseId, 'decided_at' => now(), 'updated_at' => now()]);
        foreach ([ExemptionRequest::class, TransferRequest::class, WaiverRequest::class] as $model) {
            $model::query()->where('student_id', $studentId)->whereNotIn('status', ['Approved', 'Rejected', 'Abandoned'])
                ->update(['status' => 'Rejected', 'updated_at' => now()]);
        }
    }
}
