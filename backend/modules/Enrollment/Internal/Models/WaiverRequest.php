<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;

/**
 * @property int $id
 * @property int $student_id
 * @property int $curriculum_version_id
 * @property int $target_course_id
 * @property string $target_course_code
 * @property int $credit_hours
 * @property int $prior_course_id
 * @property string $prior_course_code
 * @property int $prior_course_level
 * @property string $prior_result_source
 * @property int|null $prior_enrollment_id
 * @property string|null $prior_letter_grade
 * @property array<string, mixed> $evidence
 * @property string|null $student_note
 * @property WaiverRequestStatus $status
 * @property int $revision
 * @property Carbon|null $submitted_at
 * @property float|null $coverage_percentage
 * @property string|null $equivalency_note
 * @property int|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, WaiverRequestTransition> $transitions
 * @property-read WaiverRequestTransition|null $latestTransition
 * @property-read WaiverDecision|null $decision
 */
class WaiverRequest extends Model
{
    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new \LogicException('Waiver requests cannot be hard-deleted; abandon an editable Draft instead.');
        });
    }

    protected $fillable = [
        'student_id', 'curriculum_version_id', 'target_course_id', 'target_course_code',
        'credit_hours', 'prior_course_id', 'prior_course_code', 'prior_course_level',
        'prior_result_source', 'prior_enrollment_id', 'prior_letter_grade', 'evidence',
        'student_note', 'status', 'revision', 'submitted_at', 'coverage_percentage',
        'equivalency_note', 'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'student_id' => 'integer', 'curriculum_version_id' => 'integer',
            'target_course_id' => 'integer', 'credit_hours' => 'integer',
            'prior_course_id' => 'integer', 'prior_course_level' => 'integer',
            'prior_enrollment_id' => 'integer', 'evidence' => 'array',
            'status' => WaiverRequestStatus::class, 'revision' => 'integer',
            'submitted_at' => 'datetime', 'coverage_percentage' => 'float',
            'verified_by' => 'integer', 'verified_at' => 'datetime',
        ];
    }

    /** @return HasMany<WaiverRequestTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(WaiverRequestTransition::class)->orderBy('id');
    }

    /** @return HasOne<WaiverRequestTransition, $this> */
    public function latestTransition(): HasOne
    {
        return $this->hasOne(WaiverRequestTransition::class)->latestOfMany('id');
    }

    /** @return HasOne<WaiverDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(WaiverDecision::class);
    }
}
