<?php

namespace Modules\Catalog\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Internal\Models\Course;

/**
 * A Course in the catalog — a standalone teachable unit. Wrapped in the
 * { data: ... } envelope; both language names are always returned and the SPA
 * picks by locale. `department_id` is null for a university-wide Course (the SPA
 * renders that as "university-wide"). The Lecture/Tutorial/Lab/Total breakdown is
 * returned whole so callers see the credit-hour shape (ADR-0010). A Course has NO
 * Category of its own — Category is per Program (Curriculum Entry, ticket 05).
 *
 * @mixin Course
 */
class CourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Course $course */
        $course = $this->resource;

        return [
            'id' => $course->getKey(),
            'department_id' => $course->department_id,
            'code' => $course->code,
            'name_en' => $course->name_en,
            'name_ar' => $course->name_ar,
            'level' => $course->level->value,
            'lecture_ch' => $course->lecture_ch,
            'tutorial_ch' => $course->tutorial_ch,
            'lab_ch' => $course->lab_ch,
            'total_ch' => $course->total_ch,
            'is_active' => $course->is_active,
        ];
    }
}
