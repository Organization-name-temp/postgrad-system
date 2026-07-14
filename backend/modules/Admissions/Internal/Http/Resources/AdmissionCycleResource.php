<?php

namespace Modules\Admissions\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admissions\Internal\Models\AdmissionCycle;

/**
 * An Admission Cycle in the { data: ... } envelope. Both language names are
 * always returned (the SPA picks by locale). `is_open` is the derived open state
 * (never a stored column, ADR-0012); the applicant-facing registration screen
 * relies on it via the public open-cycle lookup.
 *
 * @mixin AdmissionCycle
 */
class AdmissionCycleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var AdmissionCycle $cycle */
        $cycle = $this->resource;

        return [
            'id' => $cycle->getKey(),
            'intake_term' => $cycle->intake_term->value,
            'year' => $cycle->year,
            'name_en' => $cycle->name_en,
            'name_ar' => $cycle->name_ar,
            'opens_at' => $cycle->opens_at->toIso8601String(),
            'closes_at' => $cycle->closes_at->toIso8601String(),
            'is_active' => $cycle->is_active,
            'is_open' => $cycle->is_open,
        ];
    }
}
