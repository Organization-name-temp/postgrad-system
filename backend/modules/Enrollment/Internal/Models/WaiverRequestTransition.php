<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;

/**
 * @property int $id
 * @property int $waiver_request_id
 * @property WaiverRequestStatus|null $from_status
 * @property WaiverRequestStatus $to_status
 * @property string $action
 * @property int $actor_user_id
 * @property int $revision
 * @property array<string, mixed> $evidence_snapshot
 * @property array<string, mixed>|null $verification_snapshot
 * @property string|null $action_id
 * @property string|null $reason
 * @property Carbon $created_at
 * @property-read WaiverRequest $request
 */
class WaiverRequestTransition extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Waiver request transitions are append-only.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Waiver request transitions are append-only.');
        });
    }

    protected $fillable = [
        'waiver_request_id', 'from_status', 'to_status', 'action', 'actor_user_id',
        'revision', 'evidence_snapshot', 'verification_snapshot', 'action_id', 'reason', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'waiver_request_id' => 'integer', 'from_status' => WaiverRequestStatus::class,
            'to_status' => WaiverRequestStatus::class, 'actor_user_id' => 'integer',
            'revision' => 'integer', 'evidence_snapshot' => 'array',
            'verification_snapshot' => 'array', 'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<WaiverRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(WaiverRequest::class, 'waiver_request_id');
    }
}
