<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\ClassSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Enrollment\Internal\Support\SessionSchedule;

/**
 * One meeting of a {@see CourseOffering} on a calendar date — a row of the attendance
 * register (ADR-0019, ticket 08). Generated idempotently from the Offering's weekly meeting
 * rows across its Semester dates (the pure {@see SessionSchedule}
 * seam); the meeting time is snapshotted onto the session so it survives a later edit to the
 * Offering's meeting pattern.
 *
 * The assigned Instructor may **cancel** a session — a cancelled session ({@see $cancelled_at}
 * set) never counts in any attendance denominator (the holiday answer, so no holiday
 * calendar) and cancellation is correctable. Each active Enrollment gets one binary
 * {@see AttendanceMark} per session.
 *
 * @property int $id
 * @property int $course_offering_id
 * @property Carbon $session_date
 * @property string $starts_at
 * @property string $ends_at
 * @property Carbon|null $cancelled_at
 */
class ClassSession extends Model
{
    /** @use HasFactory<ClassSessionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'course_offering_id',
        'session_date',
        'starts_at',
        'ends_at',
        'cancelled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'course_offering_id' => 'integer',
            'session_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ClassSessionFactory
    {
        return ClassSessionFactory::new();
    }

    /**
     * The Offering this session belongs to — an intra-module reference with a real FK.
     *
     * @return BelongsTo<CourseOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    /**
     * The present/absent marks recorded against this session.
     *
     * @return HasMany<AttendanceMark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(AttendanceMark::class);
    }

    /** Whether the session was cancelled — it then counts in no attendance denominator. */
    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /**
     * Constrain a query to sessions that still count — everything not cancelled. The
     * attendance percentage tallies over exactly this set (ADR-0019).
     *
     * @param  Builder<ClassSession>  $query
     */
    public function scopeCounted(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    /**
     * This session as the array the SPA register grid renders — the weekday derived from the
     * date (so the client labels it with the shared weekday i18n keys), times trimmed to
     * "HH:MM", and the cancelled flag.
     *
     * @return array{id: int, date: string, weekday: int, starts_at: string, ends_at: string, cancelled: bool}
     */
    public function toRegisterArray(): array
    {
        return [
            'id' => (int) $this->getKey(),
            'date' => $this->session_date->toDateString(),
            'weekday' => $this->session_date->dayOfWeek,
            'starts_at' => substr((string) $this->starts_at, 0, 5),
            'ends_at' => substr((string) $this->ends_at, 0, 5),
            'cancelled' => $this->isCancelled(),
        ];
    }
}
