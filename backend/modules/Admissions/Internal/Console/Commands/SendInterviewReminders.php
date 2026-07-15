<?php

namespace Modules\Admissions\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * `admissions:send-interview-reminders` (ticket 11) — a manually-run nudge for upcoming
 * interviews. It raises an {@see NotificationType::InterviewReminder} to both the
 * applicant and the assigned interviewer for every not-yet-completed interview starting
 * inside the reminder window (config `interview_reminder_window_hours`, default 48h) —
 * run it two days out for the ~48h reminder and again a day out for the ~24h one. Like
 * its overdue-flagging sibling it only emits notifications: no application or interview
 * status changes, nothing escalates (spec §SLAs / §Out of Scope).
 *
 * It carries no sent-marker — a hand-run reminder has no need of one — so re-running
 * inside the window re-notifies; that is intended.
 */
class SendInterviewReminders extends Command
{
    /** @var string */
    protected $signature = 'admissions:send-interview-reminders';

    /** @var string */
    protected $description = 'Remind applicants and interviewers of interviews coming up within the reminder window; changes no status.';

    public function handle(Notifier $notifier): int
    {
        $windowEnd = now()->addHours((int) config('admissions.interview_reminder_window_hours'));

        // Upcoming (not past, not yet completed) interviews starting inside the window,
        // with their slot (for the time + interviewer) loaded in one read.
        $interviews = Interview::query()
            ->whereNull('completed_at')
            ->whereHas('slot', fn ($query) => $query->whereBetween('scheduled_at', [now(), $windowEnd]))
            ->with(['slot', 'application'])
            ->orderBy('id')
            ->get();

        foreach ($interviews as $interview) {
            $slot = $interview->slot;
            $application = $interview->application;

            if ($slot === null || $application === null) {
                continue;
            }

            $payload = [
                'application_id' => $application->getKey(),
                'scheduled_at' => $slot->scheduled_at->toIso8601String(),
            ];

            // The applicant (attending) and the assigned interviewer (conducting) both
            // get the same reminder — each holds a bell.
            $notifier->notify((int) $application->user_id, NotificationType::InterviewReminder, $payload);
            $notifier->notify($slot->interviewer_id, NotificationType::InterviewReminder, $payload);
        }

        $this->info("Reminded of {$interviews->count()} upcoming interview(s).");

        return self::SUCCESS;
    }
}
