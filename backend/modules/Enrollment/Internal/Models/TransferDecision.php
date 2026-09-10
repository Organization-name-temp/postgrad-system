<?php

namespace Modules\Enrollment\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Catalog\Public\Enums\CourseCategory;

/**
 * One immutable effective Transfer decision. It recognizes a target Curriculum Course
 * without manufacturing an Enrollment or Grade.
 *
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
 * @property list<int> $document_ids
 * @property string $normalized_grade
 * @property int $verified_by_user_id
 * @property Carbon $verified_at
 * @property int $approved_by_user_id
 * @property Carbon $approved_at
 * @property int $transfer_request_id
 */
class TransferDecision extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new \LogicException('Effective Transfer decisions are immutable.');
        });
        static::deleting(static function (): never {
            throw new \LogicException('Effective Transfer decisions are immutable.');
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'student_id', 'curriculum_version_id', 'target_course_id', 'target_course_code',
        'target_category', 'target_credit_hours', 'external_institution', 'external_course_code',
        'external_course_name', 'source_credit_hours', 'external_grade', 'document_ids',
        'normalized_grade', 'verified_by_user_id', 'verified_at', 'approved_by_user_id',
        'approved_at', 'transfer_request_id',
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
            'approved_by_user_id' => 'integer',
            'approved_at' => 'datetime',
            'transfer_request_id' => 'integer',
        ];
    }
}
