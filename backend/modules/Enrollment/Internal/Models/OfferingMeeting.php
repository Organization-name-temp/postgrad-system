<?php

namespace Modules\Enrollment\Internal\Models;

use Database\Factories\OfferingMeetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Enrollment\Internal\Enums\Weekday;

/**
 * One weekly meeting slot of a {@see CourseOffering} — a weekday and a start/end time
 * (ADR-0017). Class Sessions (ticket 08) are generated from these rows across the
 * Semester's dates. Times are stored as `time` columns and surface as "HH:MM".
 *
 * @property int $id
 * @property int $course_offering_id
 * @property Weekday $weekday
 * @property string $starts_at
 * @property string $ends_at
 */
class OfferingMeeting extends Model
{
    /** @use HasFactory<OfferingMeetingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'weekday',
        'starts_at',
        'ends_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weekday' => Weekday::class,
        ];
    }

    protected static function newFactory(): OfferingMeetingFactory
    {
        return OfferingMeetingFactory::new();
    }

    /**
     * The Offering this slot belongs to.
     *
     * @return BelongsTo<CourseOffering, $this>
     */
    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }

    /**
     * This slot as the `{weekday, starts_at, ends_at}` array the SPA renders — the weekday
     * as its integer value and the times trimmed to "HH:MM". The single home for the
     * meeting's wire shape, shared by the eligible-list and registration-request-line
     * resources so they cannot drift.
     *
     * @return array{weekday: int, starts_at: string, ends_at: string}
     */
    public function toSlotArray(): array
    {
        return [
            'weekday' => $this->weekday->value,
            'starts_at' => substr($this->starts_at, 0, 5),
            'ends_at' => substr($this->ends_at, 0, 5),
        ];
    }
}
