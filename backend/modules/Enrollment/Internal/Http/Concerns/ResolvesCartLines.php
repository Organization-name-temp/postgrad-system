<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\GraduationProgress;
use Modules\AcademicStanding\Public\ProposedCourse;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\RegistrationMode;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationController;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationReviewController;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\OfferingMeeting;
use Modules\Enrollment\Internal\Support\CartLine;
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
    /** @param list<CartLine> $lines */
    protected function exceptionsFor(
        StudentSummary $student,
        SemesterSummary $semester,
        array $lines,
        GraduationProgress $progress,
    ): CreditHoursExceptions {
        $eligibility = $progress->underloadEligibility(
            $student->studentId,
            $student->enrollmentTerm === $semester->nameEn,
            array_map(fn (CartLine $line): ProposedCourse => new ProposedCourse(
                courseId: $line->courseId,
                category: match ($line->classification) {
                    EnrollmentClassification::Preparatory => CreditHourCategory::Preparatory,
                    EnrollmentClassification::Core => CreditHourCategory::ProgramCore,
                    default => CreditHourCategory::ResearchElective,
                },
                creditHours: $line->totalCh,
                forCredit: $line->mode !== RegistrationMode::Audit,
            ), $lines),
        );

        return new CreditHoursExceptions(
            firstSemester: $eligibility->firstSemesterCompletion,
            approachingGraduationCompletion: $eligibility->approachingGraduationCompletion,
        );
    }
}
