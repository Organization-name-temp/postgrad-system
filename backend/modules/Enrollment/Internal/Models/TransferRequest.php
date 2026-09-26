<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;

/**
 * @property int $id
 * @property int $student_id
 * @property int $curriculum_version_id
 * @property int $target_course_id
 * @property string $target_course_code
 * @property CourseCategory $target_category
 * @property int $target_credit_hours
 * @property string $external_institution
 * @property string $external_course_code
 * @property string $external_course_name
 * @property string $source_credit_hours
 * @property string $external_grade
 * @property list<string> $document_ids
 * @property string|null $student_note
 * @property string|null $normalized_grade
 * @property int|null $verified_by_user_id
 * @property Carbon|null $verified_at
 * @property TransferRequestStatus $status
 * @property int $revision
 * @property Carbon|null $submitted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read TransferRequestTransition|null $latestTransition
 */
class TransferRequest extends Model
{
    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new \LogicException('Transfer requests cannot be hard-deleted; abandon an editable Draft instead.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'student_id', 'curriculum_version_id', 'target_course_id', 'target_course_code',
        'target_category', 'target_credit_hours', 'external_institution', 'external_course_code',
        'external_course_name', 'source_credit_hours', 'external_grade', 'document_ids',
        'student_note', 'normalized_grade', 'verified_by_user_id', 'verified_at',
        'status', 'revision', 'submitted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'curriculum_version_id' => 'integer',
            'target_course_id' => 'integer',
            'target_category' => CourseCategory::class,
            'target_credit_hours' => 'integer',
            'source_credit_hours' => 'decimal:2',
            'document_ids' => 'array',
            'verified_by_user_id' => 'integer',
            'verified_at' => 'datetime',
            'status' => TransferRequestStatus::class,
            'revision' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return HasMany<TransferRequestTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(TransferRequestTransition::class)->orderBy('id');
    }

    /** @return HasOne<TransferRequestTransition, $this> */
    public function latestTransition(): HasOne
    {
        return $this->hasOne(TransferRequestTransition::class)
            ->select(['transfer_request_transitions.id', 'transfer_request_transitions.transfer_request_id', 'transfer_request_transitions.reason'])
            ->latestOfMany('id');
    }

    /** @return HasOne<TransferDecision, $this> */
    public function decision(): HasOne
    {
        return $this->hasOne(TransferDecision::class);
    }
}
