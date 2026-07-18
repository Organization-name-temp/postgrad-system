<?php

namespace Modules\Admissions\Internal\Models;

use Database\Factories\InterviewSlotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Modules\Admissions\Internal\Enums\InterviewMode;

/**
 * One interview appointment in the pool Student Affairs sets up (ticket 09): a time,
 * a duration, an assigned interviewer (a faculty User referenced by opaque id — no
 * Eloquent relation across the Identity seam, ADR-0002), and a mode with its matching
 * coordinate (an in-person `location` or a virtual `meeting_link`).
 *
 * Capacity is exactly one. A slot is "open" while no {@see Interview} points at it and
 * "taken" once one does — derived from the {@see self::interview()} relation, never a
 * stored flag, so a reschedule that repoints an interview away frees the former slot
 * automatically.
 *
 * @property int $id
 * @property Carbon $scheduled_at
 * @property int $duration_minutes
 * @property int $interviewer_id
 * @property InterviewMode $mode
 * @property ?string $location
 * @property ?string $meeting_link
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InterviewSlot extends Model
{
    /** @use HasFactory<InterviewSlotFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'scheduled_at',
        'duration_minutes',
        'interviewer_id',
        'mode',
        'location',
        'meeting_link',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'interviewer_id' => 'integer',
            'mode' => InterviewMode::class,
        ];
    }

    protected static function newFactory(): InterviewSlotFactory
    {
        return InterviewSlotFactory::new();
    }

    /**
     * The interview booked into this slot, if any. A slot's capacity is one, so this
     * is a {@see HasOne}; `null` means the slot is open.
     *
     * @return HasOne<Interview, $this>
     */
    public function interview(): HasOne
    {
        return $this->hasOne(Interview::class);
    }

    /**
     * Whether the slot is available to be scheduled (or rescheduled) into — i.e. no
     * interview currently points at it. Queries the relation so a freshly created or
     * just-freed slot reports correctly without the caller pre-loading it.
     */
    public function isOpen(): bool
    {
        return $this->interview()->doesntExist();
    }

    /**
     * Limit a query to open slots — those with no interview booked. Used by the SA
     * scheduling picker and the applicant's reschedule picker.
     *
     * @param  Builder<InterviewSlot>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereDoesntHave('interview');
    }
}
