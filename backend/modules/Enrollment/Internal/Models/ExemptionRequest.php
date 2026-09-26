<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\ExemptionRequestStatus;

/**
 * @property int $id
 * @property int $student_id
 * @property int $curriculum_version_id
 * @property int $target_course_id
 * @property string $target_course_code
 * @property PreparatoryRequirementRole $preparatory_requirement_role
 * @property int $credit_hours
 * @property array<string, mixed> $evidence
 * @property string|null $student_note
 * @property ExemptionRequestStatus $status
 * @property int $revision
 * @property Carbon|null $submitted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ExemptionRequestTransition|null $latestTransition
 */
class ExemptionRequest extends Model
{
    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new \LogicException('Exemption requests cannot be hard-deleted; abandon an editable Draft instead.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'student_id', 'curriculum_version_id', 'target_course_id', 'target_course_code',
        'preparatory_requirement_role', 'credit_hours', 'evidence', 'student_note',
        'status', 'revision', 'submitted_at',
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
            'evidence' => 'array',
            'status' => ExemptionRequestStatus::class,
            'revision' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return HasMany<ExemptionRequestTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(ExemptionRequestTransition::class)->orderBy('id');
    }

    /** @return HasOne<ExemptionRequestTransition, $this> */
    public function latestTransition(): HasOne
    {
        return $this->hasOne(ExemptionRequestTransition::class)
            ->select([
                'exemption_request_transitions.id',
                'exemption_request_transitions.exemption_request_id',
                'exemption_request_transitions.reason',
            ])
            ->latestOfMany('id');
    }

    /** @return HasOne<ExemptionDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(ExemptionDecision::class, 'manual_request_id');
    }
}
