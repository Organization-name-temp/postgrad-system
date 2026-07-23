<?php

namespace Modules\Catalog\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;

/**
 * A Course in the catalog — a standalone teachable unit. Wrapped in the
 * { data: ... } envelope; both language names are always returned and the SPA
 * picks by locale. `department_id` is null for a university-wide Course (the SPA
 * renders that as "university-wide"). The Lecture/Tutorial/Lab/Total breakdown is
 * returned whole so callers see the credit-hour shape (ADR-0010). A Course has NO
 * Category of its own — Category is per Program (Curriculum Entry, ticket 05).
 *
 * When the `curriculumEntries` relation is loaded (the Course detail endpoint), the
 * resource also exposes `placements` — which Programs and Categories the Course
 * appears in — powering the detail view's "appears in" section (FR-PCM-002). The
 * list endpoints don't load it, so their payload is unchanged.
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
            // The optional Research Type (PBL / Seminar), or null when the Course is
            // not a research course (ticket 02, BR-25).
            'research_type' => $course->research_type?->value,
            'lecture_ch' => $course->lecture_ch,
            'tutorial_ch' => $course->tutorial_ch,
            'lab_ch' => $course->lab_ch,
            'total_ch' => $course->total_ch,
            'is_active' => $course->is_active,
            'placements' => $this->whenLoaded('curriculumEntries', fn (): array => $course->curriculumEntries
                // Deterministic order: by Program code, then Category.
                ->sortBy(fn (CurriculumEntry $entry): string => $entry->program->code.' '.$entry->category->value)
                ->map(fn (CurriculumEntry $entry): array => [
                    'category' => $entry->category->value,
                    'program' => [
                        'id' => $entry->program->getKey(),
                        'code' => $entry->program->code,
                        'name_en' => $entry->program->name_en,
                        'name_ar' => $entry->program->name_ar,
                    ],
                ])
                ->values()
                ->all()),
        ];
    }
}
