<?php

namespace Modules\Grading\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Public\Contracts\DepartmentHeadAssignments;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * `grading:flag-late-sheets` (ticket 09) — a manually-run nudge for every Course Offering whose
 * Semester's grade-submission deadline has passed and which still has **no Finalized Grade
 * Sheet**, notifying the Instructor and the Offering's routed Department Head.
 *
 * It **changes nothing**: the Phase-2 `admissions:flag-overdue` SLA-aging pattern, and a
 * deliberate choice over a hard gate. An Instructor locked out of submitting past the deadline
 * could not deliver grades at all, which strands students' records behind an admin ticket, and
 * no SRS criterion asks for one. Like every hand-run flag in this system it carries **no
 * sent-marker**, so re-running re-notifies — intended, since the operator chooses when to run it.
 *
 * "Delivered" is *any* Finalized round, of any kind (ADR-0023 — an Offering accumulates rounds,
 * and one settled by an Incomplete resolution or a Correction is not late). A round merely
 * Submitted is **not** delivered: it is still sitting in a Department Head's queue, which is
 * exactly the situation the Head's copy of this notification exists to unblock.
 */
class FlagLateSheets extends Command
{
    /** @var string */
    protected $signature = 'grading:flag-late-sheets';

    /** @var string */
    protected $description = 'Flag course offerings past their semester grade-submission deadline with no finalized grade sheet; changes no state.';

    public function handle(
        Notifier $notifier,
        EnrollmentRecords $enrollments,
        AcademicCalendar $calendar,
        CourseCatalog $courses,
        DepartmentHeadAssignments $heads,
    ): int {
        $offerings = $enrollments->allOfferings();
        if ($offerings === []) {
            $this->info('Flagged 0 late grade sheet(s).');

            return self::SUCCESS;
        }

        // Batched reads for the whole sweep rather than one per Offering: the Semesters and
        // Courses through their owning modules' facades (ADR-0012), and the Offerings already
        // carrying a Finalized round from Grading's own table.
        $semesters = $calendar->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->semesterId,
            $offerings,
        ))));
        $resolved = $courses->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->courseId,
            $offerings,
        ))));
        $settled = GradeSheet::query()
            ->where('status', GradeSheetStatus::Finalized)
            ->whereIn('course_offering_id', array_map(
                fn (OfferingSummary $offering): int => $offering->id,
                $offerings,
            ))
            ->pluck('course_offering_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        $flagged = 0;

        foreach ($offerings as $offering) {
            $semester = $semesters[$offering->semesterId] ?? null;
            if ($semester === null || ! $semester->hasGradeSubmissionDeadlinePassed(now())) {
                continue;
            }

            if ($settled->has($offering->id)) {
                continue;
            }

            $course = $resolved[$offering->courseId] ?? null;
            $payload = [
                'course_offering_id' => $offering->id,
                'course_code' => $course?->code,
                'deadline' => $semester->gradeSubmissionDeadlineAt->toIso8601String(),
            ];

            $notifier->notify($offering->instructorId, NotificationType::GradesOverdue, $payload);

            // The same nullable Department routing every Sheet notification uses: a NULL-Department
            // Preparatory Course matches the university-wide Head assignment with no special case
            // (ADR-0010). A Course that no longer resolves has no routing Department at all, so
            // only its Instructor is nudged rather than every university-wide Head.
            if ($course !== null) {
                foreach ($heads->headsForDepartment($course->departmentId) as $headUserId) {
                    $notifier->notify($headUserId, NotificationType::GradesOverdue, $payload);
                }
            }

            $flagged++;
        }

        $this->info("Flagged {$flagged} late grade sheet(s).");

        return self::SUCCESS;
    }
}
