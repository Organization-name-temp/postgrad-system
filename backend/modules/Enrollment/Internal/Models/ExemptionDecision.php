<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\CreditDecisionType;
use Modules\Enrollment\Internal\Enums\ExemptionSource;

/**
 * One immutable effective Exemption decision. It is standalone academic credit,
 * never an Enrollment and never updated in place (ADR-0030).
 *
 * @property int $id
 * @property int $student_id
 * @property int $curriculum_version_id
 * @property int $target_course_id
 * @property string $target_course_code
 * @property PreparatoryRequirementRole $preparatory_requirement_role
 * @property int $credit_hours
 * @property ExemptionSource $source
 * @property list<string> $reason_codes
 * @property array<string, mixed> $source_evidence
 * @property int|null $admission_application_id
 * @property int|null $manual_request_id
 * @property Carbon $approved_at
 */
class ExemptionDecision extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Effective Exemption decisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Effective Exemption decisions are immutable.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'curriculum_version_id',
        'target_course_id',
        'target_course_code',
        'preparatory_requirement_role',
        'credit_hours',
        'source',
        'reason_codes',
        'source_evidence',
        'admission_application_id',
        'manual_request_id',
        'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'curriculum_version_id' => 'integer',
            'target_course_id' => 'integer',
            'preparatory_requirement_role' => PreparatoryRequirementRole::class,
            'credit_hours' => 'integer',
            'source' => ExemptionSource::class,
            'reason_codes' => 'array',
            'source_evidence' => 'array',
            'admission_application_id' => 'integer',
            'manual_request_id' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    /** @return HasOne<CreditDecisionRevocation, $this> */
    public function revocation(): HasOne
    {
        return $this->hasOne(CreditDecisionRevocation::class, 'decision_id')
            ->where('decision_type', CreditDecisionType::Exemption->value);
    }

    /** @param Builder<ExemptionDecision> $query */
    public function scopeEffective(Builder $query): void
    {
        $query->whereDoesntHave('revocation');
    }
}
