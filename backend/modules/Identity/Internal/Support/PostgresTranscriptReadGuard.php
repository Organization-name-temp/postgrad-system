<?php

namespace Modules\Identity\Internal\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Identity\Public\TranscriptReadGuard;

final class PostgresTranscriptReadGuard implements TranscriptReadGuard
{
    public function lock(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Transcript read guards require an active transaction.');
        }
        DB::statement('LOCK TABLE students, users, user_roles IN SHARE MODE');
    }
}
