<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\CreditDecisionType;

/**
 * Append-only fact that ends one immutable credit decision's academic effect.
 *
 * @property int $id
 * @property CreditDecisionType $decision_type
 * @property int $decision_id
 * @property int $student_id
 * @property int $target_course_id
 * @property int|null $revoked_by_user_id
 * @property string $reason
 * @property Carbon $created_at
 */
class CreditDecisionRevocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'decision_type', 'decision_id', 'student_id', 'target_course_id',
        'revoked_by_user_id', 'reason', 'created_at',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Credit decision revocations are append-only.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Credit decision revocations are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'decision_type' => CreditDecisionType::class,
            'decision_id' => 'integer', 'student_id' => 'integer',
            'target_course_id' => 'integer', 'revoked_by_user_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
