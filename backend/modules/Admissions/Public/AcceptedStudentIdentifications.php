<?php

namespace Modules\Admissions\Public;

/** Authoritative accepted-Application evidence; null means missing or ambiguous. */
interface AcceptedStudentIdentifications
{
    public function typeFor(int $userId, int $admissionCycleId, string $identificationNumber): ?string;
}
