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
}
