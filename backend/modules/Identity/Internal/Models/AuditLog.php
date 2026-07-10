<?php

namespace Modules\Identity\Internal\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit entry. There is no update path: $timestamps is disabled and
 * only created_at is written. Writes go through the AuditLogger contract.
 *
 * @property int|null $actor_user_id
 * @property string $action
 * @property array<string, mixed>|null $metadata
 */
class AuditLog extends Model
{
    protected $table = 'audit_log';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'actor_user_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
