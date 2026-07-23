<?php

namespace Modules\Calendar\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Calendar\Internal\Models\Semester;

/**
 * A Semester in the { data: ... } envelope. Both language names are always returned
 * (derived from term + year — never stored, ADR-0018) so the SPA picks by locale,
 * along with all five period windows. `is_registration_open` is the derived
 * functional predicate (never a stored column, the `AdmissionCycle::isOpen()`
 * precedent); the SPA derives the display state (upcoming / registration / running /
 * ended) from the dates, keeping the server the authority on the raw facts.
 *
 * @mixin Semester
 */
class SemesterResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Semester $semester */
        $semester = $this->resource;

        return [
            'id' => $semester->getKey(),
            'term' => $semester->term->value,
            'year' => $semester->year,
            'name_en' => $semester->name_en,
            'name_ar' => $semester->name_ar,
            'starts_at' => $semester->starts_at->toIso8601String(),
            'ends_at' => $semester->ends_at->toIso8601String(),
            'registration_opens_at' => $semester->registration_opens_at->toIso8601String(),
            'registration_closes_at' => $semester->registration_closes_at->toIso8601String(),
            'add_drop_ends_at' => $semester->add_drop_ends_at->toIso8601String(),
            'late_add_ends_at' => $semester->late_add_ends_at->toIso8601String(),
            'withdrawal_deadline_at' => $semester->withdrawal_deadline_at->toIso8601String(),
            'is_registration_open' => $semester->is_registration_open,
        ];
    }
}
