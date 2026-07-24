<?php

namespace Modules\Enrollment\Internal\Support;

/**
 * A single weekly meeting slot — a weekday plus a start/end time expressed as minutes
 * from midnight — and the one place the domain answers "do two meetings clash?".
 *
 * This is the interval-overlap seam the spec calls for (§Implementation Decisions,
 * ADR-0017): ticket 03 uses it to reject a Course Offering whose own meeting rows
 * overlap each other, and a later ticket reuses the very same {@see self::overlaps()}
 * primitive to detect a student's schedule clash across two Offerings' meeting sets.
 * Kept as a small immutable value object (not folded into the Eloquent models) so the
 * arithmetic can be unit-tested directly, without the HTTP stack or the database.
 *
 * Times are half-open intervals `[start, end)`: two slots that merely touch at an
 * endpoint (10:00–11:00 and 11:00–12:00) do NOT clash.
 */
final readonly class MeetingTime
{
    public function __construct(
        public int $weekday,
        public int $startMinute,
        public int $endMinute,
    ) {}

    /**
     * Build a slot from clock strings ("HH:MM" or "HH:MM:SS"). Parsing is naive on
     * purpose — the caller validates the format at the HTTP boundary; this only maps a
     * well-formed time to minutes.
     */
    public static function fromClock(int $weekday, string $start, string $end): self
    {
        return new self($weekday, self::toMinutes($start), self::toMinutes($end));
    }

    /** Whether the slot is well-formed — it ends strictly after it starts. */
    public function isWellFormed(): bool
    {
        return $this->endMinute > $this->startMinute;
    }

    /**
     * Whether this slot clashes with another: same weekday and intersecting half-open
     * minute intervals. This is the single overlap primitive reused for both the
     * offering self-overlap check and (later) student schedule clashes.
     */
    public function overlaps(self $other): bool
    {
        if ($this->weekday !== $other->weekday) {
            return false;
        }

        return $this->startMinute < $other->endMinute
            && $other->startMinute < $this->endMinute;
    }

    /**
     * Whether any two slots in the list clash — the self-overlap the offering CRUD
     * rejects with a 422 (ADR-0017). Reuses the pairwise {@see self::overlaps()}
     * primitive, so a set and a pair answer "do these clash?" the same way.
     *
     * @param  list<self>  $slots
     */
    public static function hasOverlap(array $slots): bool
    {
        $count = count($slots);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($slots[$i]->overlaps($slots[$j])) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function toMinutes(string $clock): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $clock));

        return $hours * 60 + $minutes;
    }
}
