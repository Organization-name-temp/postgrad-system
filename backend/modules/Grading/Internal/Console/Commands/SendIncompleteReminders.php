<?php

namespace Modules\Grading\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Grading\Internal\Support\IncompleteDeadlineResolver;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * `grading:send-incomplete-reminders` (ticket 05, SRS §4.7.3.4) — a manually-run nudge for
 * every outstanding Incomplete whose derived deadline is exactly one of the configured day
 * offsets (28/14/7/1) away, so running it daily produces precisely the four reminders the SRS
 * describes. It carries **no sent-marker** — the Phase-2 `SendInterviewReminders` precedent
 * that a hand-run reminder needs none — so re-running on the same day re-notifies; that is
 * intended, and it stays silent on any other day. An Incomplete with no deadline yet (its next
 * Semester does not exist in the Calendar) is silently skipped: there is nothing to count down
 * to, and declaring one is never blocked for the same omission.
 *
 * Changes no state whatsoever — the SRS reserves that for the conversion command alone.
 */
class SendIncompleteReminders extends Command
{
    /** @var string */
    protected $signature = 'grading:send-incomplete-reminders';

    /** @var string */
    protected $description = 'Remind students of an outstanding Incomplete whose resolution deadline is 28/14/7/1 days away; changes no status.';

    public function handle(
        Notifier $notifier,
        EnrollmentRecords $enrollments,
        StudentDirectory $students,
        IncompleteDeadlineResolver $deadlines,
    ): int {
        $offsets = array_map('intval', (array) config('grading.incomplete_reminder_offsets_days'));

        $outstanding = Incomplete::query()
            ->whereNull('resolved_grade_id')
            ->whereNull('converted_at')
            ->orderBy('id')
            ->get();

        $reminded = 0;

        foreach ($outstanding as $incomplete) {
            $enrollment = $enrollments->findEnrollment((int) $incomplete->enrollment_id);
            if ($enrollment === null) {
                continue;
            }

            $deadline = $deadlines->forEnrollmentSemester($enrollment->semesterId);
            if ($deadline === null) {
                continue;
            }

            $today = now()->startOfDay();
            $deadlineDay = $deadline->startOfDay();
            if ($deadlineDay->lessThan($today)) {
                continue;
            }

            // Non-negative by construction ($deadlineDay is today or later), so the exact-day
            // match against the configured offsets never needs a signed diff.
            $daysRemaining = (int) $today->diffInDays($deadlineDay);
            if (! in_array($daysRemaining, $offsets, true)) {
                continue;
            }

            $summary = $students->findByStudentId($enrollment->studentId);
            if ($summary === null) {
                continue;
            }

            $notifier->notify($summary->userId, NotificationType::IncompleteReminder, [
                'enrollment_id' => $enrollment->id,
                'deadline' => $deadline->toIso8601String(),
                'days_remaining' => $daysRemaining,
            ]);
            $reminded++;
        }

        $this->info("Reminded of {$reminded} outstanding incomplete(s).");

        return self::SUCCESS;
    }
}
