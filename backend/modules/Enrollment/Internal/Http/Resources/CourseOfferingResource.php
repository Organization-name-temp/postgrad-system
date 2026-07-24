<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Identity\Public\UserSummary;

/**
 * A {@see CourseOffering} in the `{ data: ... }` envelope — the row an administrator
 * scans when managing what runs this semester. It carries the opaque references
 * (semester / course / instructor ids) plus their resolved display summaries, the
 * optional seat capacity, and the weekly meeting slots.
 *
 * The Course and Instructor are referenced by id (no FK, ADR-0012) and resolved
 * through the Catalog\Public and Identity\Public facades, stashed on the model by the
 * controller (see {@see ResolvesOfferingRefs})
 * — so a list costs two facade reads, not two per row. Meeting times surface as "HH:MM"
 * strings and the weekday as its integer value (the client maps it to a bilingual label).
 *
 * @mixin CourseOffering
 */
class CourseOfferingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CourseOffering $offering */
        $offering = $this->resource;

        $course = $offering->getAttribute('resolved_course');
        $instructor = $offering->getAttribute('resolved_instructor');

        return [
            'id' => $offering->getKey(),
            'semester_id' => $offering->semester_id,
            'course_id' => $offering->course_id,
            'instructor_id' => $offering->instructor_id,
            'capacity' => $offering->capacity,
            'course' => $course instanceof CourseSummary ? $course->toArray() : null,
            'instructor' => $instructor instanceof UserSummary ? $instructor->toArray() : null,
            'meetings' => $offering->meetings->map(fn (OfferingMeeting $meeting): array => [
                'id' => $meeting->getKey(),
                'weekday' => $meeting->weekday->value,
                'starts_at' => substr($meeting->starts_at, 0, 5),
                'ends_at' => substr($meeting->ends_at, 0, 5),
            ])->all(),
        ];
    }
}
