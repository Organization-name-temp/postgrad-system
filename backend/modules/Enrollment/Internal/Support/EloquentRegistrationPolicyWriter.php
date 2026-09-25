<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Public\RegistrationPolicyWriter;

final class EloquentRegistrationPolicyWriter implements RegistrationPolicyWriter
{
    public function recordStanding(int $studentId, string $standing, ?int $registrationCeilingCh): void
    {
        DB::table('registration_policy_projections')->updateOrInsert(
            ['student_id' => $studentId],
            ['standing' => $standing, 'registration_ceiling_ch' => $registrationCeilingCh, 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /**
     * @param  list<int>  $remainingPreparatoryTargets
     * @param  list<int>  $remainingExactTargets
     */
    public function recordUnderloadBasis(int $studentId, array $remainingPreparatoryTargets, array $remainingExactTargets, int $remainingElectiveCh, int $totalRemainingCh): void
    {
        DB::table('registration_policy_projections')->updateOrInsert(
            ['student_id' => $studentId],
            [
                'remaining_preparatory_targets' => json_encode($remainingPreparatoryTargets, JSON_THROW_ON_ERROR),
                'remaining_exact_targets' => json_encode($remainingExactTargets, JSON_THROW_ON_ERROR),
                'remaining_elective_ch' => $remainingElectiveCh,
                'total_remaining_ch' => $totalRemainingCh,
                'created_at' => now(), 'updated_at' => now(),
            ],
        );
    }
}
