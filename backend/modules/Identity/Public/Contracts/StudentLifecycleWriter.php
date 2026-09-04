<?php

namespace Modules\Identity\Public\Contracts;

use Modules\Identity\Public\StudentSummary;

/**
 * Identity-owned writer for the one-way operational lifecycle projection.
 * The future final-Termination workflow calls this inside its own transaction,
 * after acquiring the shared Student guard and revalidating its authority.
 */
interface StudentLifecycleWriter
{
    /** Serialize on the Student anchor and idempotently mark it Terminated. */
    public function terminate(int $studentId): ?StudentSummary;
}
