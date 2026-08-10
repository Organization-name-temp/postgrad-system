<?php

namespace Modules\Grading\Internal\Models;

use Database\Factories\IncompleteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An Incomplete (SRS §4.7.3, phase 4 ticket 05) — one Instructor attestation that a Student
 * completed at least the configured share of the coursework but could not finish, declared on
 * a Sheet line in place of a mark. It carries no deadline column: the resolution deadline is
 * always **derived** from the Calendar (the Semester following the Enrollment's, plus 63 days)
 * and never stored, so correcting a Semester's start date moves every outstanding deadline
 * with it (ADR-0018).
 *
 * `enrollment_id` and `declared_by` are opaque cross-module references with no foreign key
 * (ADR-0012); `declared_on_grade_sheet_id` (the Initial round it was declared on),
 * `resolution_grade_sheet_id` (the later `IncompleteResolution` round, once opened) and
 * `resolved_grade_id` (the resolving Grade, once finalized) are intra-module and carry real
 * FKs.
 *
 * An Incomplete is **outstanding** while neither settled path has happened
 * ({@see self::isOutstanding()}); it settles exactly one of two ways — resolved (a Grade
 * lands, restoring the pre-Incomplete Course Status) or converted (Fail Withdrawal, the only
 * path there is, SRS §4.7.3.3) — never both.
 *
 * @property int $id
 * @property int $declared_on_grade_sheet_id
 * @property int $enrollment_id
 * @property int $declared_by
 * @property int $completion_percentage
 * @property string $reason
 * @property int|null $resolution_grade_sheet_id
 * @property int|null $resolved_grade_id
 * @property Carbon|null $converted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Incomplete extends Model
{
    /** @use HasFactory<IncompleteFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'declared_on_grade_sheet_id',
        'enrollment_id',
        'declared_by',
        'completion_percentage',
        'reason',
        'resolution_grade_sheet_id',
        'resolved_grade_id',
        'converted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'declared_on_grade_sheet_id' => 'integer',
            'enrollment_id' => 'integer',
            'declared_by' => 'integer',
            'completion_percentage' => 'integer',
            'resolution_grade_sheet_id' => 'integer',
            'resolved_grade_id' => 'integer',
            'converted_at' => 'datetime',
        ];
    }

    protected static function newFactory(): IncompleteFactory
    {
        return IncompleteFactory::new();
    }

    /**
     * The Initial round this Incomplete was declared on — a same-module reference with a real
     * FK.
     *
     * @return BelongsTo<GradeSheet, $this>
     */
    public function declaringSheet(): BelongsTo
    {
        return $this->belongsTo(GradeSheet::class, 'declared_on_grade_sheet_id');
    }

    /**
     * The `IncompleteResolution` round opened to resolve this Incomplete, or `null` before one
     * has been.
     *
     * @return BelongsTo<GradeSheet, $this>
     */
    public function resolutionSheet(): BelongsTo
    {
        return $this->belongsTo(GradeSheet::class, 'resolution_grade_sheet_id');
    }

    /**
     * The resolving Grade, once one has been finalized — `null` while unresolved (or while
     * converted instead).
     *
     * @return BelongsTo<Grade, $this>
     */
    public function resolvedGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'resolved_grade_id');
    }

    /** Whether this Incomplete is still unsettled — neither resolved nor converted. */
    public function isOutstanding(): bool
    {
        return $this->resolved_grade_id === null && $this->converted_at === null;
    }

    /**
     * The declaration's wire shape — the completion percentage and reason an Instructor
     * attested to, shared by the Sheet line read and the Department Head's review (ticket 05).
     *
     * @return array{completion_percentage: int, reason: string}
     */
    public function toLineArray(): array
    {
        return [
            'completion_percentage' => $this->completion_percentage,
            'reason' => $this->reason,
        ];
    }
}
