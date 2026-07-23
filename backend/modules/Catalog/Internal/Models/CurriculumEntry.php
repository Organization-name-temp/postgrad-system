<?php

namespace Modules\Catalog\Internal\Models;

use Database\Factories\CurriculumEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Public\Enums\CourseCategory;

/**
 * A Curriculum Entry — the placement of one Course into one Program under one
 * Category (ADR-0010, CONTEXT.md). The Category lives here, not on the Course, so a
 * single Course can be placed in several Programs under different Categories.
 *
 * @property int $id
 * @property int $program_id
 * @property int $course_id
 * @property CourseCategory $category
 * @property int $ordinal
 */
class CurriculumEntry extends Model
{
    /** @use HasFactory<CurriculumEntryFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'program_id',
        'course_id',
        'category',
        'ordinal',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => CourseCategory::class,
            'ordinal' => 'integer',
        ];
    }

    protected static function newFactory(): CurriculumEntryFactory
    {
        return CurriculumEntryFactory::new();
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
