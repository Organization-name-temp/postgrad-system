<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\ApplicationStatusTransitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\ApplicationStatus;

/**
 * One append-only row in an Application's status timeline (ticket 07): a single
 * workflow move recorded by an {@see Application} named action — the state it left,
 * the state it entered, who moved it, an optional note (a reviewer's return
 * instructions or a decision reason), and when. It is the applicant's visible
 * history and the decision audit trail, and is deliberately distinct from Identity's
 * Audit Log (which records identity/security events, not workflow moves).
 *
 * The log is never edited: there are no timestamps to touch and no write path but
 * append (the API exposes only reads). The actor is held as a plain `actor_user_id`
 * — Admissions references users by opaque id and never loads Identity's User model
 * (ADR-0002, the seam) — and may be null for an automatic step with no human actor.
 *
 * @property int $id
 * @property int $application_id
 * @property ?ApplicationStatus $from_status
 * @property ApplicationStatus $to_status
 * @property ?int $actor_user_id
 * @property ?string $note
 * @property Carbon $occurred_at
 */
class ApplicationStatusTransition extends Model
{
    /** @use HasFactory<ApplicationStatusTransitionFactory> */
    use HasFactory;

    /** Append-only: a transition is never touched after it is written. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'application_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'note',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ApplicationStatusTransitionFactory
    {
        return ApplicationStatusTransitionFactory::new();
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
