<?php

namespace Modules\Grading\Internal\Listeners;

use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Internal\Events\GradeFinalized;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * Notifies every graded Student when a {@see GradeFinalized} event fires — the ticket-03
 * side-effect of a finalize (ADR-0023). Registered against the event in
 * GradingServiceProvider and resolved from the container, it runs synchronously on dispatch,
 * strictly after the finalize transaction committed.
 *
 * One notification per Grade on the round, carrying the letter and whether it fell below C+
 * (Appendix C's `min_grade_per_course`) — the single payload SRS user story 41/42 both read,
 * so no second notification type is needed for the must-retake warning ticket 07 surfaces
 * downstream.
 */
class SendGradeFinalizedNotifications
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly EnrollmentRecords $enrollments,
        private readonly StudentDirectory $students,
    ) {}

    public function handle(GradeFinalized $event): void
    {
        $sheet = GradeSheet::query()->with('grades')->find($event->gradeSheetId);
        if ($sheet === null) {
            return;
        }

        $threshold = (float) config('grading.min_grade_per_course');

        $studentIds = [];
        /** @var array<int, array{student_id: int, grade: Grade}> $byEnrollment */
        $byEnrollment = [];
        foreach ($sheet->grades as $grade) {
            $enrollment = $this->enrollments->findEnrollment((int) $grade->enrollment_id);
            if ($enrollment === null) {
                continue;
            }

            $studentIds[] = $enrollment->studentId;
            $byEnrollment[$grade->enrollment_id] = ['student_id' => $enrollment->studentId, 'grade' => $grade];
        }

        $summaries = $this->students->findManyByStudentId(array_values(array_unique($studentIds)));

        foreach ($byEnrollment as $entry) {
            $summary = $summaries[$entry['student_id']] ?? null;
            if ($summary === null) {
                continue;
            }

            $grade = $entry['grade'];
            $this->notifier->notify($summary->userId, NotificationType::GradeFinalized, [
                'enrollment_id' => (int) $grade->enrollment_id,
                'letter_grade' => $grade->letter_grade->value,
                'below_c_plus' => (float) $grade->gpa_points < $threshold,
            ]);
        }
    }
}
