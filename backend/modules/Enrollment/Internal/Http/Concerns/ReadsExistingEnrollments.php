<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Illuminate\Support\Collection;
use Modules\Enrollment\Internal\Enums\CourseStatus;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Http\Controllers\RegistrationReviewController;
use Modules\Enrollment\Internal\Http\Controllers\ScheduleChangeController;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Support\ExistingEnrollments;

/**
 * Reads a Student's already-minted Enrollment facts into the pure {@see ExistingEnrollments}
 * value object that the cart rules re-validate against — shared by the advisor mint
 * ({@see RegistrationReviewController}) and the period-gated schedule changes
 * ({@see ScheduleChangeController}), so "what is already on the Student's record?" is read
 * one way. Requires the using class to also compose {@see ResolvesCartLines} (for
 * {@see ResolvesCartLines::meetingSlots()}).
 */
trait ReadsExistingEnrollments
{
    /**
     * The Student's already-minted Enrollment facts for re-validation: every enrolled
     * Offering (duplicate), active counts by classification (caps), prior for-credit
     * attempts per course (re-registration routing), outstanding Incompletes by course, the
     * active schedule in the target Semester (clash), and the active credit-hour load in the
     * target Semester (ceiling / drop floor-guard). One read of the Student's Enrollments;
     * "active" excludes a Withdrawal (W).
     */
    protected function existingEnrollments(int $studentId, int $targetSemesterId): ExistingEnrollments
    {
        /** @var Collection<int, Enrollment> $enrollments */
        $enrollments = Enrollment::query()
            ->where('student_id', $studentId)
            ->with('offering.meetings')
            ->orderBy('id')
            ->get();

        $active = $enrollments->filter(
            fn (Enrollment $enrollment): bool => $enrollment->course_status !== CourseStatus::Withdrawn,
        );

        $priorForCredit = [];
        $priorEarned = [];
        $outstandingIncompleteCourseIds = [];
        $meetings = [];
        $activeCreditHours = 0;
        foreach ($active as $enrollment) {
            $offering = $enrollment->offering;
            if ($offering === null) {
                continue;
            }

            $courseId = (int) $offering->course_id;
            if ($enrollment->course_status->burnsRetakeAllowance()) {
                $priorForCredit[$courseId] = ($priorForCredit[$courseId] ?? 0) + 1;
                $priorEarned[$courseId] = $enrollment->earned;
            }
            if ($enrollment->course_status === CourseStatus::Incomplete) {
                $outstandingIncompleteCourseIds[$courseId] = $courseId;
            }
            if ((int) $offering->semester_id === $targetSemesterId) {
                $activeCreditHours += (int) $enrollment->credit_hours;
                foreach ($this->meetingSlots($offering) as $slot) {
                    $meetings[] = $slot;
                }
            }
        }

        return new ExistingEnrollments(
            enrolledOfferingIds: $enrollments->pluck('course_offering_id')->map(fn ($id): int => (int) $id)->all(),
            externalElectiveCount: $active->filter(
                fn (Enrollment $e): bool => $e->classification === EnrollmentClassification::ExternalElective
                    && $e->earned !== false,
            )->count(),
            researchCourseCount: $active->filter(fn (Enrollment $e): bool => $e->classification === EnrollmentClassification::ResearchCourse)->count(),
            priorForCreditCountByCourse: $priorForCredit,
            priorEarnedByCourse: $priorEarned,
            outstandingIncompleteCourseIds: array_values($outstandingIncompleteCourseIds),
            meetings: $meetings,
            activeCreditHours: $activeCreditHours,
        );
    }
}
