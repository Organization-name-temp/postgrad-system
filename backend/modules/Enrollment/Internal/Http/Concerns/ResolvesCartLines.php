<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Modules\Calendar\Public\SemesterSummary;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationController;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationReviewController;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Support\CreditHoursExceptions;
use Modules\Enrollment\Internal\Support\MeetingTime;
use Modules\Identity\Public\StudentSummary;

/**
 * The cart-resolution helpers shared by the Student's cart submission
 * ({@see RegistrationController}) and the
 * Advisor's mint-time re-validation
 * ({@see RegistrationReviewController}), so the
 * rule is expressed once (spec §Rules engines — the CH validator and the clash seam are the
 * same at submit and at mint).
 */
trait ResolvesCartLines
{
    /**
     * The Offering's weekly meetings as pure {@see MeetingTime} slots — the shape the clash
     * check reads.
     *
     * @return list<MeetingTime>
     */
    protected function meetingSlots(CourseOffering $offering): array
    {
        return $offering->meetings->map(fn (OfferingMeeting $meeting): MeetingTime => MeetingTime::fromClock(
            $meeting->weekday->value,
            $meeting->starts_at,
            $meeting->ends_at,
        ))->all();
    }

    /**
     * The credit-hour exceptions for a registration: the first-semester waiver applies when
     * the target Semester is the Student's enrollment term (matched by natural key — the
     * Semester's display name equals the stored term, ADR-0018). Phase 5 adds the
     * approaching-graduation exception here.
     */
    protected function exceptionsFor(StudentSummary $student, SemesterSummary $semester): CreditHoursExceptions
    {
        return new CreditHoursExceptions(
            firstSemester: $student->enrollmentTerm === $semester->nameEn,
        );
    }
}
