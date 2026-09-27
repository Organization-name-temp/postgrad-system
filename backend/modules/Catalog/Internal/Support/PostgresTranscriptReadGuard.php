<?php

namespace Modules\Catalog\Internal\Support;

use Illuminate\Support\Facades\DB;
use LogicException;
use Modules\Catalog\Public\TranscriptReadGuard;

final class PostgresTranscriptReadGuard implements TranscriptReadGuard
{
    public function lock(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Transcript read guards require an active transaction.');
        }
        DB::statement('LOCK TABLE faculties, departments, programs, courses, curriculum_entries, catalog_versions IN SHARE MODE');
    }
}
