<?php

namespace Modules\Grading\Internal\Models;

use Database\Factories\GradeSheetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

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
 * The two-party signature loop (ticket 03) lives on {@see self::sign()}, {@see self::finalize()}
 * and {@see self::returnToInstructor()} — each a named action over the {@see GradeSheetStatus}
 * transition map, throwing a 409 on an illegal move (the Admissions ApplicationStatus
 * precedent). `signed_by`/`signed_at` and `reviewed_by`/`reviewed_at`/`review_note` are opaque
 * Identity user references with no foreign key (ADR-0012).
 *
 * @property int $id
 * @property int $course_offering_id
 * @property GradeSheetKind $kind
 * @property GradeSheetStatus $status
 * @property int|null $signed_by
 * @property Carbon|null $signed_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
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
        'signed_by',
        'signed_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'course_offering_id' => 'integer',
            'kind' => GradeSheetKind::class,
            'status' => GradeSheetStatus::class,
            'signed_by' => 'integer',
            'signed_at' => 'datetime',
            'reviewed_by' => 'integer',
            'reviewed_at' => 'datetime',
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

    /**
     * The Instructor signs the round as one act (SRS §4.7.1.4): Draft or Returned →
     * Submitted. The caller (the controller) has already refused a blank line before this is
     * reached — this method only enforces the state machine, not the roster.
     */
    public function sign(int $actorId): void
    {
        $this->transitionTo(GradeSheetStatus::Submitted);
        $this->signed_by = $actorId;
        $this->signed_at = now();
        $this->save();
    }

    /**
     * The Department Head finalizes the round: Submitted → Finalized, permanently closed
     * (ADR-0023). Writing `earned` onto each Enrollment and firing `GradeFinalized` are the
     * caller's job (the controller, inside the same transaction) — this method only performs
     * the state transition and records the reviewer.
     */
    public function finalize(int $actorId): void
    {
        $this->transitionTo(GradeSheetStatus::Finalized);
        $this->reviewed_by = $actorId;
        $this->reviewed_at = now();
        $this->review_note = null;
        $this->save();
    }

    /**
     * The Department Head returns the round with a note instead of finalizing it: Submitted →
     * Returned. The round becomes editable and re-signable again (ADR-0023) — re-signing routes
     * back to the same queue since {@see self::sign()} accepts both Draft and Returned.
     */
    public function returnToInstructor(int $actorId, string $note): void
    {
        $this->transitionTo(GradeSheetStatus::Returned);
        $this->reviewed_by = $actorId;
        $this->reviewed_at = now();
        $this->review_note = $note;
        $this->save();
    }

    /**
     * Move to `$to` if the current status allows it, or raise the 409 every illegal Sheet
     * transition gets (the Admissions ApplicationStatus precedent) — naming both ends so the
     * message is diagnosable from the response alone.
     */
    private function transitionTo(GradeSheetStatus $to): void
    {
        if (! $this->status->canTransitionTo($to)) {
            throw new ConflictHttpException(
                __('grading.sheet.illegal_transition', ['from' => $this->status->value, 'to' => $to->value]),
            );
        }

        $this->status = $to;
    }
}
