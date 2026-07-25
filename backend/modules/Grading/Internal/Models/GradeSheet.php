<?php

namespace Modules\Grading\Internal\Models;

use Database\Factories\GradeSheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;

/**
 * A Grade Sheet — one **submission round** for one Course Offering (ADR-0023). It is the unit
 * the Instructor signs and the Department Head reviews, and it carries the state machine;
 * individual {@see Grade} rows carry none.
 *
 * Its `course_offering_id` is an opaque cross-module reference with no foreign key (ADR-0012):
 * the Offering is Enrollment-owned and resolved through `Enrollment\Public`. The reference is
 * deliberately **not unique** — an Offering accumulates rounds. The one exception is a partial
 * unique index on the **Initial** round, which ADR-0023 says an Offering has exactly one of;
 * that constraint is what makes the lazy `firstOrCreate` on first open safe against two
 * concurrent opens.
 *
 * @property int $id
 * @property int $course_offering_id
 * @property GradeSheetKind $kind
 * @property GradeSheetStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GradeSheet extends Model
{
    /** @use HasFactory<GradeSheetFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'course_offering_id',
        'kind',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'course_offering_id' => 'integer',
            'kind' => GradeSheetKind::class,
            'status' => GradeSheetStatus::class,
        ];
    }

    protected static function newFactory(): GradeSheetFactory
    {
        return GradeSheetFactory::new();
    }

    /**
     * This round's Grades — a same-module reference with a real FK, ordered by Enrollment so
     * the Sheet reads in a stable order.
     *
     * @return HasMany<Grade, $this>
     */
    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class)->orderBy('enrollment_id');
    }

    /** Whether marks may still be written on this round ({@see GradeSheetStatus::acceptsMarks()}). */
    public function acceptsMarks(): bool
    {
        return $this->status->acceptsMarks();
    }
}
