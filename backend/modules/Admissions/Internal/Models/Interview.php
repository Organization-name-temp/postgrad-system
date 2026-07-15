<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\InterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\InterviewStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * The interview booked for an {@see Application} (ticket 09) — one per application.
 * Student Affairs creates it by scheduling an approved applicant into an open
 * {@see InterviewSlot}; the applicant then confirms it or reschedules it once; the
 * slot's assigned interviewer records an evaluation; and Student Affairs marks it
 * complete, advancing the application to `interview_completed`.
 *
 * Its {@see self::$status} (pending / confirmed) tracks only the applicant's
 * confirmation — the Application's own status stays `interview_scheduled` for the
 * whole arranged-interview phase. Both the confirmation guard and the single-reschedule
 * cap are enforced by the named methods here; an illegal move is a 409 (a conflict
 * with the interview's current state), consistent with the Application status machine.
 *
 * `interview_slot_id` points at the *current* slot; a reschedule repoints it, which
 * frees the former slot ({@see InterviewSlot::isOpen()} reads the relation).
 *
 * @property int $id
 * @property int $application_id
 * @property int $interview_slot_id
 * @property InterviewStatus $status
 * @property int $reschedule_count
 * @property ?int $evaluation_score
 * @property ?string $evaluation_notes
 * @property ?Carbon $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Interview extends Model
{
    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    /**
     * The applicant may reschedule an interview exactly once (BR-11): a second
     * reschedule is refused. `reschedule_count` starts at 0 and is bumped by
     * {@see self::rescheduleTo()}.
     */
    public const int MAX_RESCHEDULES = 1;

    /**
     * Only the booking references are mass-assignable. `status`, `reschedule_count`,
     * the evaluation fields, and `completed_at` are moved solely by this model's
     * guarded methods — never by mass assignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'interview_slot_id',
    ];

    /**
     * A fresh interview is pending the applicant's confirmation, with no reschedules
     * yet used. Set here (in-memory) so a just-created interview reports both without
     * a reload; neither is fillable.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => InterviewStatus::PendingConfirmation->value,
        'reschedule_count' => 0,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => InterviewStatus::class,
            'interview_slot_id' => 'integer',
            'reschedule_count' => 'integer',
            'evaluation_score' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): InterviewFactory
    {
        return InterviewFactory::new();
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<InterviewSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(InterviewSlot::class, 'interview_slot_id');
    }

    /** Whether the applicant still has their one reschedule available. */
    public function canReschedule(): bool
    {
        return $this->reschedule_count < self::MAX_RESCHEDULES;
    }

    /**
     * The applicant confirms the booked interview: {@see InterviewStatus::PendingConfirmation}
     * → {@see InterviewStatus::Confirmed}. Confirming an already-confirmed interview
     * is a 409 (there is nothing to confirm).
     */
    public function confirm(): void
    {
        if ($this->status !== InterviewStatus::PendingConfirmation) {
            throw new ConflictHttpException(__('admissions.interview.not_pending'));
        }

        $this->status = InterviewStatus::Confirmed;
        $this->save();
    }

    /**
     * The applicant moves the interview to another open slot, using their single
     * allowed reschedule (BR-11). Repoints {@see self::$interview_slot_id} to the new
     * slot, bumps {@see self::$reschedule_count}, and returns the interview to
     * {@see InterviewStatus::PendingConfirmation} (the new slot needs confirming). A
     * second reschedule is refused with a 409. The caller has already validated that
     * the target slot exists and is open.
     */
    public function rescheduleTo(InterviewSlot $slot): void
    {
        if (! $this->canReschedule()) {
            throw new ConflictHttpException(__('admissions.interview.reschedule_exhausted'));
        }

        $this->interview_slot_id = $slot->getKey();
        $this->reschedule_count++;
        $this->status = InterviewStatus::PendingConfirmation;
        $this->save();
    }

    /**
     * The assigned interviewer records their evaluation (score + optional notes). The
     * row-level authorization — that the caller *is* the slot's interviewer — is
     * enforced by the controller before this is reached. An evaluation cannot be
     * recorded once the interview is complete (409): the record is closed.
     */
    public function recordEvaluation(int $score, ?string $notes): void
    {
        if ($this->completed_at !== null) {
            throw new ConflictHttpException(__('admissions.interview.already_completed'));
        }

        $this->evaluation_score = $score;
        $this->evaluation_notes = $notes;
        $this->save();
    }

    /**
     * Student Affairs marks the interview complete, stamping {@see self::$completed_at}.
     * The matching Application transition ({@see Application::completeInterview()}) is
     * performed together in one transaction by the controller. Completing an
     * already-complete interview is a 409.
     */
    public function markCompleted(): void
    {
        if ($this->completed_at !== null) {
            throw new ConflictHttpException(__('admissions.interview.already_completed'));
        }

        $this->completed_at = now();
        $this->save();
    }
}
