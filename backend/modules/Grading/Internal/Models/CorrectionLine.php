<?php

namespace Modules\Grading\Internal\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One selected line of a Correction round (phase 4 ticket 06) — the Enrollment whose Grade the
 * Instructor intends to change on that round. The selection exists even while the round is
 * still blank, so a Correction can carry only the affected lines without reopening the whole
 * class's settled Sheet (ADR-0023).
 *
 * @property int $id
 * @property int $grade_sheet_id
 * @property int $enrollment_id
 */
class CorrectionLine extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'grade_sheet_id',
        'enrollment_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'grade_sheet_id' => 'integer',
            'enrollment_id' => 'integer',
        ];
    }

    /**
     * The Correction round this selected line belongs to — a same-module reference with a real
     * FK.
     *
     * @return BelongsTo<GradeSheet, $this>
     */
    public function sheet(): BelongsTo
    {
        return $this->belongsTo(GradeSheet::class, 'grade_sheet_id');
    }
}
