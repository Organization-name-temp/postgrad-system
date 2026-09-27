<?php

namespace Modules\Grading\Internal\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Grading\Public\TranscriptReadGuard;

final class PostgresTranscriptReadGuard implements TranscriptReadGuard
{
    public function lock(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Transcript read guards require an active transaction.');
        }
        DB::statement('LOCK TABLE grade_sheets, grades, incompletes IN SHARE MODE');
    }
}
