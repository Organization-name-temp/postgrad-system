<?php

namespace Modules\Enrollment\Public;

interface RegistrationPolicyWriter
{
    public function recordStanding(int $studentId, string $standing, ?int $registrationCeilingCh): void;

    /**
     * @param  list<int>  $remainingPreparatoryTargets
     * @param  list<int>  $remainingExactTargets
     */
    public function recordUnderloadBasis(int $studentId, array $remainingPreparatoryTargets, array $remainingExactTargets, int $remainingElectiveCh, int $totalRemainingCh): void;
}
