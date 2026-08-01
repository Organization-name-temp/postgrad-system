<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Support\EligibilityClassifier;
use Modules\Identity\Public\UserSummary;

/**
 * One line on a Student's eligible-course list (ticket 04) — a Course Offering the
 * Student may register for this semester, tagged with the Enrollment Classification the
 * {@see EligibilityClassifier} derived for them.
 * The SPA groups these lines by {@see $classification} into the five sections.
 *
 * The Course and Instructor are referenced by opaque id (no FK, ADR-0012) and resolved
 * through the Catalog\Public / Identity\Public facades, stashed on the model by the
 * controller (see {@see ResolvesOfferingRefs}),
 * so the whole list costs two facade reads. Each line carries the display facts a
 * Student chooses on — instructor, meeting times, seats remaining — plus the Course's
 * total CH, the value the cart will sum against the load rules (FR-REG-001, ADR-0017).
 *
 * @mixin CourseOffering
 */
class EligibleCourseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var CourseOffering $offering */
        $offering = $this->resource;

        $course = $offering->getAttribute('resolved_course');
        $instructor = $offering->getAttribute('resolved_instructor');

        /** @var EnrollmentClassification $classification */
        $classification = $offering->getAttribute('classification');

        return [
            'offering_id' => $offering->getKey(),
            'classification' => $classification->value,
            'capacity' => $offering->capacity,
            'seats_remaining' => $offering->seatsRemaining(),
            'total_ch' => $course instanceof CourseSummary ? $course->totalCh : null,
            'must_retake' => (bool) $offering->getAttribute('must_retake'),
            'course' => $course instanceof CourseSummary ? $course->toArray() : null,
            'instructor' => $instructor instanceof UserSummary ? $instructor->toArray() : null,
            'meetings' => $offering->meetings->map(fn (OfferingMeeting $meeting): array => [
                'weekday' => $meeting->weekday->value,
                'starts_at' => substr($meeting->starts_at, 0, 5),
                'ends_at' => substr($meeting->ends_at, 0, 5),
            ])->all(),
        ];
    }
}
