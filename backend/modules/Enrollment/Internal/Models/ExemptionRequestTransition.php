<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\ExemptionRequestStatus;

/**
 * @property int $id
 * @property int $exemption_request_id
 * @property ExemptionRequestStatus|null $from_status
 * @property ExemptionRequestStatus $to_status
 * @property string $action
 * @property int $actor_user_id
 * @property int $revision
 * @property array<string, mixed> $evidence_snapshot
 * @property string|null $action_id
 * @property string|null $reason
 * @property Carbon $created_at
 */
class ExemptionRequestTransition extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Exemption request transitions are append-only.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Exemption request transitions are append-only.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'exemption_request_id', 'from_status', 'to_status', 'action', 'actor_user_id',
        'revision', 'evidence_snapshot', 'action_id', 'reason', 'system_cause_type',
        'system_cause_id', 'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'exemption_request_id' => 'integer',
            'from_status' => ExemptionRequestStatus::class,
            'to_status' => ExemptionRequestStatus::class,
            'actor_user_id' => 'integer',
            'system_cause_id' => 'integer',
            'revision' => 'integer',
            'evidence_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ExemptionRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(ExemptionRequest::class, 'exemption_request_id');
    }
}
