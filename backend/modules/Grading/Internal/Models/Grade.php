<?php

namespace Modules\Grading\Internal\Models;

use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Grading\Internal\Enums\LetterGrade;

/**
 * A Grade — one Instructor-entered **Mark** for one Enrollment on one {@see GradeSheet} round,
 * with the Letter Grade and GPA points the ADR-0021 scale derives from it. The academic result
 * and nothing else: no assessment components, weights or curves, and no letter override.
 *
 * The letter and points are **frozen** at the moment the mark is recorded
 * ({@see self::derivedFrom()}), so a later change to the scale never rewrites a grade already
 * given. Because there is no
 * override, `letter_grade == LetterGrade::forMark(mark)` holds for every row at the moment it
 * was written — correcting a letter means correcting the mark.
 *
 * `enrollment_id` is an opaque cross-module reference with no foreign key (ADR-0012). There is
 * no `superseded` column: the current Grade for an Enrollment is derived as the one on its
 * latest Finalized Sheet (ADR-0023).
 *
 * @property int $id
 * @property int $grade_sheet_id
 * @property int $enrollment_id
 * @property string $mark
 * @property LetterGrade $letter_grade
 * @property string $gpa_points
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'grade_sheet_id',
        'enrollment_id',
        'mark',
        'letter_grade',
        'gpa_points',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grade_sheet_id' => 'integer',
            'enrollment_id' => 'integer',
            'mark' => 'decimal:2',
            'letter_grade' => LetterGrade::class,
            'gpa_points' => 'decimal:2',
        ];
    }

    protected static function newFactory(): GradeFactory
    {
        return GradeFactory::new();
    }

    /**
     * The round this Grade belongs to — a same-module reference with a real FK.
     *
     * @return BelongsTo<GradeSheet, $this>
     */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(GradeSheet::class, 'grade_sheet_id');
    }

    /**
     * The attribute payload for a mark, with the Letter Grade and GPA points **derived from
     * the scale and frozen onto it** (ADR-0021). The single place a Grade's derived fields are
     * computed, so an entry, a correction and a seeder can never disagree about what a mark
     * means. The caller validates the 0–100 range first (the API returns a 422 naming the
     * field); a mark outside it throws.
     *
     * @return array{mark: float, letter_grade: string, gpa_points: float}
     */
    public static function derivedFrom(float $mark): array
    {
        $letter = LetterGrade::forMark($mark);

        return [
            'mark' => $mark,
            'letter_grade' => $letter->value,
            'gpa_points' => $letter->points(),
        ];
    }

    /**
     * The Grade's wire shape — the single home for how one graded line is exposed, so the
     * Sheet read and the per-line save response can never drift. The mark and points are
     * emitted as floats (the column is a decimal, which Eloquent hands back as a string) and
     * the letter as its plain string, so no Grading-internal type crosses to the SPA.
     *
     * @return array{mark: float, letter_grade: string, gpa_points: float}
     */
    public function toLineArray(): array
    {
        return [
            'mark' => (float) $this->mark,
            'letter_grade' => $this->letter_grade->value,
            'gpa_points' => (float) $this->gpa_points,
        ];
    }
}
