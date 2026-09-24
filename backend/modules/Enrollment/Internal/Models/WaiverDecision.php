<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\CreditDecisionType;

/**
 * @property int $id
 * @property int $waiver_request_id
 * @property int $student_id
 * @property int $curriculum_version_id
 * @property int $target_course_id
 * @property string $target_course_code
 * @property int $credit_hours
 * @property int $prior_course_id
 * @property string $prior_course_code
 * @property int $prior_course_level
 * @property string $prior_result_source
 * @property string|null $prior_letter_grade
 * @property float $coverage_percentage
 * @property int $verified_by
 * @property Carbon $verified_at
 * @property int $approved_by
 * @property Carbon $approved_at
 */
class WaiverDecision extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Effective Waiver decisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Effective Waiver decisions are immutable.');
        });
    }

    protected $fillable = [
        'waiver_request_id', 'student_id', 'curriculum_version_id', 'target_course_id',
        'target_course_code', 'credit_hours', 'prior_course_id', 'prior_course_code',
        'prior_course_level', 'prior_result_source', 'prior_letter_grade',
        'coverage_percentage', 'verified_by', 'verified_at', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'waiver_request_id' => 'integer', 'student_id' => 'integer',
            'curriculum_version_id' => 'integer', 'target_course_id' => 'integer',
            'credit_hours' => 'integer', 'prior_course_id' => 'integer',
            'prior_course_level' => 'integer', 'coverage_percentage' => 'float',
            'verified_by' => 'integer', 'verified_at' => 'datetime',
            'approved_by' => 'integer', 'approved_at' => 'datetime',
        ];
    }

    /** @return HasOne<CreditDecisionRevocation, $this> */
    public function revocation(): HasOne
    {
        return $this->hasOne(CreditDecisionRevocation::class, 'decision_id')
            ->where('decision_type', CreditDecisionType::Waiver->value);
    }

    /** @param Builder<WaiverDecision> $query */
    public function scopeEffective(Builder $query): void
    {
        $query->whereDoesntHave('revocation');
    }
}
