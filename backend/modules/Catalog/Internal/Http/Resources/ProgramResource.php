<?php

namespace Modules\Catalog\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Internal\Models\Program;

/**
 * A Program in the catalog tree — a degree offering under a Department. Wrapped in
 * the { data: ... } envelope; both language names are always returned and the SPA
 * picks by locale. The per-Category required credit hours are returned alongside
 * the derived total_required_ch (their sum), so viewers see the shape of the
 * degree at a glance (ADR-0010).
 *
 * @mixin Program
 */
class ProgramResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Program $program */
        $program = $this->resource;

        return [
            'id' => $program->getKey(),
            'department_id' => $program->department_id,
            'code' => $program->code,
            'name_en' => $program->name_en,
            'name_ar' => $program->name_ar,
            'degree_level' => $program->degree_level->value,
            'prep_required_ch' => $program->prep_required_ch,
            'core_required_ch' => $program->core_required_ch,
            'elective_required_ch' => $program->elective_required_ch,
            'total_required_ch' => $program->total_required_ch,
            'is_active' => $program->is_active,
        ];
    }
}
