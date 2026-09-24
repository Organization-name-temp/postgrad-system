<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;

/**
 * @property int $id
 * @property int $transfer_request_id
 * @property TransferRequestStatus|null $from_status
 * @property TransferRequestStatus $to_status
 * @property string $action
 * @property int|null $actor_user_id
 * @property string|null $system_cause_type
 * @property int|null $system_cause_id
 * @property int $revision
 * @property array<string, mixed> $evidence_snapshot
 * @property array<string, mixed>|null $review_snapshot
 * @property string|null $action_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
class TransferRequestTransition extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Transfer request transitions are append-only.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Transfer request transitions are append-only.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'transfer_request_id', 'from_status', 'to_status', 'action', 'actor_user_id',
        'revision', 'evidence_snapshot', 'review_snapshot', 'action_id', 'reason',
        'system_cause_type', 'system_cause_id', 'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transfer_request_id' => 'integer',
            'from_status' => TransferRequestStatus::class,
            'to_status' => TransferRequestStatus::class,
            'actor_user_id' => 'integer',
            'system_cause_id' => 'integer',
            'revision' => 'integer',
            'evidence_snapshot' => 'array',
            'review_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TransferRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(TransferRequest::class);
    }
}
