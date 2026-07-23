<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Models\RegistrationRequestLine;
use Modules\Enrollment\Internal\Support\EligibilityResolver;
use Modules\Identity\Public\UserSummary;

/**
 * One line of a submitted registration request (ticket 05) — the chosen Course Offering,
 * the requested mode (Regular / Audit), and the display facts the Student and (ticket 06)
 * the Advisor read on the request view: the resolved Course, its instructor, the weekly
 * meeting times, the course's total CH, and the Enrollment Classification the
 * {@see EligibilityResolver} derived.
 *
 * The Offering, Course and Instructor are referenced by opaque id (no FK, ADR-0012) and
 * resolved by the controller onto the line's Offering (see {@see ResolvesOfferingRefs});
 * a dropped Offering (deleted while the cart was Pending) renders as null facts rather
 * than erroring.
 *
 * @mixin RegistrationRequestLine
 */
class RegistrationRequestLineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var RegistrationRequestLine $line */
        $line = $this->resource;

        $offering = $line->offering;
        $course = $offering?->getAttribute('resolved_course');
        $instructor = $offering?->getAttribute('resolved_instructor');
        $classification = $offering?->getAttribute('classification');

        return [
            'offering_id' => $line->course_offering_id,
            'mode' => $line->mode->value,
            'classification' => $classification instanceof EnrollmentClassification ? $classification->value : null,
            'total_ch' => $course instanceof CourseSummary ? $course->totalCh : null,
            'course' => $course instanceof CourseSummary ? $course->toArray() : null,
            'instructor' => $instructor instanceof UserSummary ? $instructor->toArray() : null,
            'meetings' => $offering === null
                ? []
                : $offering->meetings->map(fn (OfferingMeeting $meeting): array => $meeting->toSlotArray())->all(),
        ];
    }
}
