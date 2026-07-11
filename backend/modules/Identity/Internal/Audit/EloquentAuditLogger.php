<?php

namespace Modules\Identity\Internal\Audit;

use Illuminate\Support\Carbon;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Public\Contracts\AuditLogger;

class EloquentAuditLogger implements AuditLogger
{
    public function record(
        string $action,
        ?int $actorUserId = null,
        array $metadata = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): void {
        AuditLog::create([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata !== [] ? $metadata : null,
            'created_at' => Carbon::now(),
        ]);
    }
}
