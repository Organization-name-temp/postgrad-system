<?php

namespace Modules\Catalog\Internal\Models;

use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Catalog\Internal\Enums\CourseLevel;

/**
 * A Course — a standalone teachable unit (ADR-0010, CONTEXT.md). It is owned by a
 * Department or is university-wide (nullable {@see self::$department_id}; NULL = a
 * shared Preparatory course). Its Lecture/Tutorial/Lab hours sum to Total (an
 * invariant enforced at the API boundary). A Course carries NO Category of its own
 * — Category is assigned per Program by a Curriculum Entry (ticket 05).
 *
 * @property int $id
 * @property int|null $department_id
 * @property string $code
 * @property string $name_en
 * @property string $name_ar
 * @property CourseLevel $level
 * @property int $lecture_ch
 * @property int $tutorial_ch
 * @property int $lab_ch
 * @property int $total_ch
 * @property bool $is_active
 */
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'department_id',
        'code',
        'name_en',
        'name_ar',
        'level',
        'lecture_ch',
        'tutorial_ch',
        'lab_ch',
        'total_ch',
        'is_active',
    ];

    /**
     * Active on creation; deactivation is an explicit action. Set in-memory (not
     * only as a DB default) so a freshly created model reports it without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'lecture_ch' => 'integer',
            'tutorial_ch' => 'integer',
            'lab_ch' => 'integer',
            'total_ch' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): CourseFactory
    {
        return CourseFactory::new();
    }

    /**
     * The owning Department, or none when the Course is university-wide
     * (department_id is NULL — the shared Preparatory courses).
     *
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * The Curriculum Entries placing this Course into Programs — the source of the
     * Course's "appears in" placements (which Programs and Categories it belongs to).
     * A Course may sit in several Programs under different Categories (ADR-0010).
     *
     * @return HasMany<CurriculumEntry, $this>
     */
    public function curriculumEntries(): HasMany
    {
        return $this->hasMany(CurriculumEntry::class);
    }
}
