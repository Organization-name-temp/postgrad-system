<?php

namespace Modules\Identity\Public\Contracts;

use Modules\Identity\Public\StudentSummary;

/**
 * Identity-owned transaction guard used to serialize lifecycle-changing workflows.
 * Callers acquire it before their own module locks and receive only a Public DTO.
 */
interface StudentGuard
{
    /** Lock and reread one Student inside the caller's active database transaction. */
    public function lock(int $studentId): ?StudentSummary;
}
