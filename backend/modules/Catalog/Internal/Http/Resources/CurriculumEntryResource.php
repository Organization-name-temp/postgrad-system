<?php

namespace Modules\Catalog\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Internal\Models\CurriculumEntry;

/**
 * A Curriculum Entry — a Course placed in a Program under a Category (ADR-0010).
 * Wrapped in the { data: ... } envelope. The linked Course is embedded (via
 * {@see CourseResource}) so the front end can render the curriculum grouped by
 * Category without a second round-trip; the Category lives on the entry, never on
 * the Course.
 *
 * @mixin CurriculumEntry
 */
class CurriculumEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CurriculumEntry $entry */
        $entry = $this->resource;

        return [
            'id' => $entry->getKey(),
            'program_id' => $entry->program_id,
            'category' => $entry->category->value,
            'preparatory_requirement_role' => $entry->preparatory_requirement_role?->value,
            'ordinal' => $entry->ordinal,
            'course' => new CourseResource($entry->course),
        ];
    }
}
