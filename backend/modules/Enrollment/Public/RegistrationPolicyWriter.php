<?php

namespace Modules\Enrollment\Public;

interface RegistrationPolicyWriter
{
    public function recordStanding(int $studentId, string $standing, ?int $registrationCeilingCh): void;
}
