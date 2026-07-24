<?php

namespace Modules\Calendar\Internal\Models;

use Database\Factories\SemesterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Calendar\Internal\Support\SemesterCalendar;
use Modules\Calendar\Public\Enums\Term;
use Modules\Calendar\Public\SemesterSummary;

/**
 * A Semester — the academic term the university runs (ADR-0018, FR-CAL-001): a
 * {@see Term} (Fall/Spring) of one year, unique together, carrying its five period
 * windows. It is the single authority every later Phase-3 record reads from
 * (offerings, registration, attendance).
 *
 * Whether registration is open, the term is running, or a given period is active is
 * always **derived** from the dates through {@see self::calendar()} — never a stored
 * flag (the `AdmissionCycle::isOpen()` precedent, ADR-0012). The bilingual display
 * name is likewise derived from term + year, not stored. A Semester is edited in
 * place, never hard-deleted.
 *
 * @property int $id
 * @property Term $term
 * @property int $year
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon $registration_opens_at
 * @property Carbon $registration_closes_at
 * @property Carbon $add_drop_ends_at
 * @property Carbon $late_add_ends_at
 * @property Carbon $withdrawal_deadline_at
 * @property-read string $name_en
 * @property-read string $name_ar
 * @property-read bool $is_registration_open
 */
class Semester extends Model
{
    /** @use HasFactory<SemesterFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'term',
        'year',
        'starts_at',
        'ends_at',
        'registration_opens_at',
        'registration_closes_at',
        'add_drop_ends_at',
        'late_add_ends_at',
        'withdrawal_deadline_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'term' => Term::class,
            'year' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'add_drop_ends_at' => 'datetime',
            'late_add_ends_at' => 'datetime',
            'withdrawal_deadline_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SemesterFactory
    {
        return SemesterFactory::new();
    }

    /**
     * The pure date logic for this Semester — the single authority for its ordering
     * invariant and every period answer. The model, its resource, and the
     * {@see SemesterSummary} facade DTO all read periods
     * through this, so a loaded model and the cross-module facade never drift.
     */
    public function calendar(): SemesterCalendar
    {
        return new SemesterCalendar(
            startsAt: $this->starts_at,
            endsAt: $this->ends_at,
            registrationOpensAt: $this->registration_opens_at,
            registrationClosesAt: $this->registration_closes_at,
            addDropEndsAt: $this->add_drop_ends_at,
            lateAddEndsAt: $this->late_add_ends_at,
            withdrawalDeadlineAt: $this->withdrawal_deadline_at,
        );
    }

    /**
     * The English display name — derived from term + year, never stored.
     *
     * @return Attribute<string, never>
     */
    protected function nameEn(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->term->labelEn().' '.$this->year);
    }

    /**
     * The Arabic display name — derived from term + year, never stored.
     *
     * @return Attribute<string, never>
     */
    protected function nameAr(): Attribute
    {
        return Attribute::make(get: fn (): string => $this->term->labelAr().' '.$this->year);
    }

    /**
     * Whether registration is open right now — active window contains the present
     * moment. Derived, never stored; mirrored by {@see self::scopeRegistrationOpen()}
     * so a query and a loaded model always agree (ADR-0012).
     *
     * @return Attribute<bool, never>
     */
    protected function isRegistrationOpen(): Attribute
    {
        return Attribute::make(get: fn (): bool => $this->calendar()->isRegistrationOpen(now()));
    }

    /**
     * Constrain a query to Semesters whose registration window contains the present
     * moment — the SQL mirror of {@see SemesterCalendar::isRegistrationOpen()}, for
     * the Calendar\Public facade's open-semester lookup.
     *
     * @param  Builder<Semester>  $query
     */
    public function scopeRegistrationOpen(Builder $query): void
    {
        $query->where('registration_opens_at', '<=', now())
            ->where('registration_closes_at', '>=', now());
    }

    /**
     * Constrain a query to the Semester(s) running right now — start ≤ now ≤ end,
     * the SQL mirror of {@see SemesterCalendar::isRunning()}.
     *
     * @param  Builder<Semester>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }
}
