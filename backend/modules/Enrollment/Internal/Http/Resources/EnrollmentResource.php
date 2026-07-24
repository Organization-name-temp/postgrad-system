<?php

namespace Modules\Enrollment\Internal\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Catalog\Public\CourseSummary;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Http\Concerns\ComputesAttendance;
use Modules\Enrollment\Internal\Http\Concerns\ResolvesOfferingRefs;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Support\AttendanceCalculator;
use Modules\Identity\Public\UserSummary;

/**
 * One minted Enrollment as it appears on the Student's My Schedule and (later) an
 * Instructor's roster (ticket 06). It renders the two **snapshotted** accounting facts —
 * the {@see CourseStatus} and the registration-time
 * credit hours — beside the live display facts read through the Catalog/Offering facades:
 * the course, its instructor, and the weekly meeting times. The classification is the value
 * frozen at the mint (ADR-0017).
 *
 * The Offering, Course and Instructor are resolved by the controller onto the Enrollment's
 * Offering ({@see ResolvesOfferingRefs}); a dropped reference renders as null facts rather
 * than erroring. The `attendance` block (ticket 08, ADR-0019) is the Student's derived
 * attendance — present/recorded counts, the display percentage, and the audit-at-risk flag —
 * stashed by {@see ComputesAttendance}; a surface
 * that does not compute it (a just-added enrollment) renders a zeroed "no data" block, never
 * null, so the typed client stays stable.
 *
 * @mixin Enrollment
 */
class EnrollmentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->resource;

        $offering = $enrollment->offering;
        $course = $offering?->getAttribute('resolved_course');
        $instructor = $offering?->getAttribute('resolved_instructor');

        return [
            'id' => $enrollment->getKey(),
            'offering_id' => $enrollment->course_offering_id,
            'course_status' => $enrollment->course_status->value,
            'classification' => $enrollment->classification->value,
            'credit_hours' => $enrollment->credit_hours,
            'course' => $course instanceof CourseSummary ? $course->toArray() : null,
            'instructor' => $instructor instanceof UserSummary ? $instructor->toArray() : null,
            'meetings' => $offering === null
                ? []
                : $offering->meetings->map(fn (OfferingMeeting $meeting): array => $meeting->toSlotArray())->all(),
            // The derived attendance block (ticket 08), stashed by ComputesAttendance on the
            // surfaces that compute it (My Schedule); the shared "no data yet" block elsewhere.
            'attendance' => $enrollment->getAttribute('attendance') ?? AttendanceCalculator::emptyBlock(),
        ];
    }
}
