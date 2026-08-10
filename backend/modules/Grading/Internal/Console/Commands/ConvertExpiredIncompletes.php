<?php

namespace Modules\Grading\Internal\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Grading\Internal\Models\Incomplete;
use Modules\Grading\Internal\Support\IncompleteDeadlineResolver;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * `grading:convert-expired-incompletes` (ticket 05, SRS §4.7.3.3) — the **one** command in the
 * system that changes state on its own rather than only notifying (every other manually-run
 * command in this build is flag-and-notify only): every outstanding Incomplete whose derived
 * deadline has passed becomes a Fail Withdrawal — the only path there is to that Course Status;
 * there is no administrative late-withdrawal path and no Instructor-assigned FW.
 *
 * "Passed" means the calendar day *after* the deadline, never the deadline day itself — the
 * resolution window includes its last day in full (Appendix D's "After deadline" annotation
 * is read as "after the Incomplete resolution deadline", not "on or after"). An Incomplete
 * whose next Semester does not exist in the Calendar yet has no deadline to measure against
 * and is **skipped and reported** — never converted, never a crash: an admin's omission never
 * costs a Student their record.
 */
class ConvertExpiredIncompletes extends Command
{
    /** @var string */
    protected $signature = 'grading:convert-expired-incompletes';

    /** @var string */
    protected $description = 'Convert every outstanding Incomplete past its resolution deadline to Fail Withdrawal; skips and reports those with no deadline yet.';

    public function handle(
        Notifier $notifier,
        EnrollmentRecords $enrollments,
        StudentDirectory $students,
        IncompleteDeadlineResolver $deadlines,
    ): int {
        $outstanding = Incomplete::query()
            ->whereNull('resolved_grade_id')
            ->whereNull('converted_at')
            ->orderBy('id')
            ->get();

        $converted = 0;
        $skipped = 0;

        foreach ($outstanding as $incomplete) {
            $enrollment = $enrollments->findEnrollment((int) $incomplete->enrollment_id);
            if ($enrollment === null) {
                continue;
            }

            $deadline = $deadlines->forEnrollmentSemester($enrollment->semesterId);
            if ($deadline === null) {
                $skipped++;
                $this->warn("Incomplete #{$incomplete->getKey()} (enrollment #{$enrollment->id}) has no next semester yet — skipped.");

                continue;
            }

            if (! now()->startOfDay()->greaterThan($deadline->startOfDay())) {
                continue;
            }

            $this->convert($incomplete, $enrollment, $enrollments);

            $summary = $students->findByStudentId($enrollment->studentId);
            if ($summary !== null) {
                $notifier->notify($summary->userId, NotificationType::IncompleteConverted, [
                    'enrollment_id' => $enrollment->id,
                ]);
            }

            $converted++;
        }

        $this->info("Converted {$converted} expired incomplete(s); skipped {$skipped} with no deadline yet.");

        return self::SUCCESS;
    }

    /** The write itself, atomic: the Course Status change and the Incomplete's own settlement. */
    private function convert(Incomplete $incomplete, EnrollmentSummary $enrollment, EnrollmentRecords $enrollments): void
    {
        DB::transaction(function () use ($incomplete, $enrollment, $enrollments): void {
            $enrollments->convertIncompleteToFailWithdrawal($enrollment->id);
            $incomplete->update(['converted_at' => now()]);
        });
    }
}
