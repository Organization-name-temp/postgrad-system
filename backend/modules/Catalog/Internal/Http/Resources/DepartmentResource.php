<?php

namespace Modules\Catalog\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Internal\Models\Department;

/**
 * A Department in the catalog tree — an academic unit within a Faculty. Wrapped in
 * the { data: ... } envelope; both language names are always returned and the SPA
 * picks by locale. `faculty_id` references its owning Faculty (ADR-0010).
 *
 * @mixin Department
 */
class DepartmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Department $department */
        $department = $this->resource;

        return [
            'id' => $department->getKey(),
            'faculty_id' => $department->faculty_id,
            'code' => $department->code,
            'name_en' => $department->name_en,
            'name_ar' => $department->name_ar,
            'is_active' => $department->is_active,
        ];
    }
}
