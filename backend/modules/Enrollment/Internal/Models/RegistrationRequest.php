<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\RegistrationRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Enums\RegistrationKind;
use Modules\Enrollment\Internal\Enums\RegistrationStatus;

/**
 * A Registration Request — a Student's submitted cart for one Semester (ADR-0017): the
 * aggregate the assigned Advisor decides line-by-line (ticket 06). It references its
 * Student and Semester by opaque id (no cross-module foreign key — ADR-0012; each is
 * validated through that module's Public facade), carries its {@see RegistrationKind} and
 * its {@see RegistrationStatus} in the review pipeline, and owns one or more
 * {@see RegistrationRequestLine} rows.
 *
 * Born {@see RegistrationStatus::Pending} on submit; there is no server-side Draft (the
 * cart lives in the SPA until submit). At most one Pending request exists per (student,
 * semester) — enforced by a partial unique index and an app-layer check — so a Rejected
 * request can be replaced by a fresh cart while the window is open.
 *
 * @property int $id
 * @property int $student_id
 * @property int $semester_id
 * @property RegistrationKind $kind
 * @property RegistrationStatus $status
 * @property string|null $advisor_note
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RegistrationRequest extends Model
{
    /** @use HasFactory<RegistrationRequestFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'student_id',
        'semester_id',
        'kind',
        'status',
        'advisor_note',
        'decided_at',
        'decided_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'semester_id' => 'integer',
            'kind' => RegistrationKind::class,
            'status' => RegistrationStatus::class,
            'decided_at' => 'datetime',
            'decided_by' => 'integer',
        ];
    }

    protected static function newFactory(): RegistrationRequestFactory
    {
        return RegistrationRequestFactory::new();
    }

    /**
     * The cart's lines — one per chosen Course Offering — ordered for a stable display.
     *
     * @return HasMany<RegistrationRequestLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RegistrationRequestLine::class)->orderBy('id');
    }

    /**
     * Constrain a query to Pending requests — the "one Pending per (student, semester)"
     * check and the advisor queue (ticket 06) both read this.
     *
     * @param  Builder<RegistrationRequest>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', RegistrationStatus::Pending);
    }

    /** Whether the request is still awaiting an Advisor's decision. */
    public function isPending(): bool
    {
        return $this->status === RegistrationStatus::Pending;
    }

    /**
     * Whether the Student already has a Pending request for the Semester — the "at most one
     * Pending per (student, semester)" guard both the Student's own submit and a staff
     * compose-on-behalf (ticket 09) read before creating one, so the rule lives once (an
     * approved Initial does not count; the partial unique index is on Pending only).
     */
    public static function hasPendingFor(int $studentId, int $semesterId): bool
    {
        return static::query()->pending()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->exists();
    }

    /**
     * Whether the Student has an **approved Initial** request for the Semester — the gate
     * for free add/drop (ticket 07, FR-REG-004): the direct add/drop actions are available
     * only after the Advisor has approved the Student's initial registration, so the
     * Advisor is never bypassed on the first schedule. Read by both the schedule view and
     * the add/drop actions, so the rule lives once.
     */
    public static function hasApprovedInitialFor(int $studentId, int $semesterId): bool
    {
        return static::query()
            ->where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('kind', RegistrationKind::Initial)
            ->where('status', RegistrationStatus::Approved)
            ->exists();
    }

    /**
     * Record the Advisor's decision on the request itself — the terminal status (Approved
     * when ≥1 line was kept, Rejected when all were trimmed), the optional note, and who
     * decided it when. The per-line outcomes and the Enrollment mint are driven by the
     * caller inside the same transaction (the ADR-0013 accept→mint precedent); this only
     * moves the aggregate's own state.
     */
    public function recordDecision(RegistrationStatus $outcome, int $decidedBy, ?string $note): void
    {
        $this->update([
            'status' => $outcome,
            'advisor_note' => $note,
            'decided_at' => now(),
            'decided_by' => $decidedBy,
        ]);
    }
}
