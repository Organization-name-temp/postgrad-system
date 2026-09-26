<?php

namespace Modules\Admissions\Internal\Enrollment;

use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Public\AcceptedStudentIdentifications;

final class EloquentAcceptedStudentIdentifications implements AcceptedStudentIdentifications
{
    public function typeFor(int $userId, int $admissionCycleId, string $identificationNumber): ?string
    {
        $applications = Application::query()
            ->where('user_id', $userId)->where('admission_cycle_id', $admissionCycleId)
            ->where('status', ApplicationStatus::Accepted->value)
            ->where('national_id', $identificationNumber)->get();

        return $applications->count() === 1 ? $applications->first()?->id_type?->value : null;
    }
}
