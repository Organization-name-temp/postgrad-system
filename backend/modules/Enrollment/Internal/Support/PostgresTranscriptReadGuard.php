<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Enrollment\Public\TranscriptReadGuard;

final class PostgresTranscriptReadGuard implements TranscriptReadGuard
{
    public function lock(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Transcript read guards require an active transaction.');
        }
        DB::statement('LOCK TABLE course_offerings, enrollments, exemption_decisions, transfer_decisions, waiver_decisions, credit_decision_revocations IN SHARE MODE');
    }
}
