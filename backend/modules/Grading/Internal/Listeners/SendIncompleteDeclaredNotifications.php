<?php

namespace Modules\Grading\Internal\Listeners;

use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Http\Controllers\DepartmentHeadReviewController;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Grading\Internal\Support\IncompleteDeadlineResolver;
use Modules\Grading\Public\Events\GradeFinalized;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * Notifies every Student whose Incomplete was just declared, when a {@see GradeFinalized}
 * event fires for an **Initial** round (ticket 05, SRS §4.7.3.2) — a second side-effect of the
 * same event {@see SendGradeFinalizedNotifications} reacts to, registered alongside it in
 * GradingServiceProvider, and just as strictly *after* the finalize transaction committed. An
 * IncompleteResolution or Correction round's finalize never declares anything, so this is a
 * no-op for those.
 *
 * The Course Status only actually becomes Incomplete at finalize
 * ({@see DepartmentHeadReviewController::finalize()}),
 * not at declaration — so this is also the correct, and only, moment to tell the Student: they
 * are notified of a decision that has actually taken effect, never a provisional one under
 * review.
 */
class SendIncompleteDeclaredNotifications
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly EnrollmentRecords $enrollments,
        private readonly StudentDirectory $students,
        private readonly IncompleteDeadlineResolver $deadlines,
    ) {}

    public function handle(GradeFinalized $event): void
    {
        $sheet = GradeSheet::query()->find($event->gradeSheetId);
        if ($sheet === null || $sheet->kind !== GradeSheetKind::Initial) {
            return;
        }

        $declared = Incomplete::query()->where('declared_on_grade_sheet_id', $sheet->getKey())->get();

        foreach ($declared as $incomplete) {
            $enrollment = $this->enrollments->findEnrollment((int) $incomplete->enrollment_id);
            if ($enrollment === null) {
                continue;
            }

            $summary = $this->students->findByStudentId($enrollment->studentId);
            if ($summary === null) {
                continue;
            }

            $deadline = $this->deadlines->forEnrollmentSemester($enrollment->semesterId);

            $this->notifier->notify($summary->userId, NotificationType::IncompleteDeclared, [
                'enrollment_id' => $enrollment->id,
                'reason' => $incomplete->reason,
                'deadline' => $deadline?->toIso8601String(),
            ]);
        }
    }
}
