<?php

namespace Modules\Catalog\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Internal\Models\Faculty;

/**
 * A Faculty in the catalog tree — a top-level academic division (FoE, BAS, CSIT).
 * Wrapped in the { data: ... } envelope by JsonResource; both language names are
 * always returned and the SPA picks by locale (ADR-0010).
 *
 * @mixin Faculty
 */
class FacultyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Faculty $faculty */
        $faculty = $this->resource;

        return [
            'id' => $faculty->getKey(),
            'code' => $faculty->code,
            'name_en' => $faculty->name_en,
            'name_ar' => $faculty->name_ar,
            'is_active' => $faculty->is_active,
        ];
    }
}
