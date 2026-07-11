<?php

namespace Modules\Identity\Public\Contracts;

/**
 * Append-only audit writer. The only way to add to the audit log; other modules
 * depend on this contract (Identity/Public), never on the storage model.
 */
interface AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        string $action,
        ?int $actorUserId = null,
        array $metadata = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): void;
}
