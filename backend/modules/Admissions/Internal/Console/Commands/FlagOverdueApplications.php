<?php

namespace Modules\Admissions\Internal\Console\Commands;

use Illuminate\Console\Command;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Support\BusinessDays;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Identity\Public\Contracts\DeanAssignments;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * `admissions:flag-overdue` (ticket 11) — a manually-run nudge, not an escalator. It
 * finds every application still waiting in a review stage past its configured SLA and
 * raises an {@see NotificationType::ApplicationOverdue} notification to the reviewer who
 * owns that stage — Student Affairs for screening, the Faculty's assigned Dean(s) for
 * Dean review. It never changes an application's status, appends no timeline row, and
 * escalates nothing automatically (spec §SLAs / §Out of Scope): the workflow advances
 * only by human action.
 *
 * Age is derived on demand — the business days (Egyptian Fri/Sat weekend excluded) since
 * the timeline entry into the stage, via {@see BusinessDays} — and never stored, so each
 * run re-reads the current state. Re-running therefore re-notifies anything still
 * overdue; that is intended for a hand-run reminder (there is no sent-marker to keep).
 *
 * Cross-module reads stay behind the Public facades (ADR-0012): reviewers through
 * Identity's {@see Directory}/{@see DeanAssignments}, an application's Faculty through
 * Catalog's {@see ProgramCatalog}.
 */
class FlagOverdueApplications extends Command
{
    /** @var string */
    protected $signature = 'admissions:flag-overdue';

    /** @var string */
    protected $description = 'Notify reviewers of applications waiting past their review SLA (business days); changes no status.';

    public function handle(
        Notifier $notifier,
        Directory $directory,
        DeanAssignments $assignments,
        ProgramCatalog $catalog,
    ): int {
        $flagged = $this->flagScreeningOverdue($notifier, $directory)
            + $this->flagDeanReviewOverdue($notifier, $assignments, $catalog);

        $this->info("Flagged {$flagged} overdue application(s).");

        return self::SUCCESS;
    }

    /**
     * Screening-stage overdue (`under_sa_review`): notify every Student Affairs staffer,
     * who share one queue (there is no per-application SA assignment). Returns how many
     * applications were flagged.
     */
    private function flagScreeningOverdue(Notifier $notifier, Directory $directory): int
    {
        $recipients = $directory->usersByRole(Role::StudentAffairs);

        if ($recipients === []) {
            return 0;
        }

        $overdue = $this->overdueIn(
            ApplicationStatus::UnderSAReview,
            (int) config('admissions.sa_screening_sla_days'),
        );

        foreach ($overdue as [$application, $ageDays]) {
            foreach ($recipients as $recipient) {
                $this->notifyOverdue($notifier, $recipient->id, $application, 'sa_screening', $ageDays);
            }
        }

        // Every overdue application was flagged: recipients is non-empty here, so each
        // one reached at least one Student Affairs staffer.
        return count($overdue);
    }

    /**
     * Dean-review-stage overdue (`under_dean_review`): notify the Dean(s) assigned to the
     * application's Faculty, resolved `application → primary_program_id → facultyId` through
     * the Catalog facade. An application with no reviewer to reach — a dropped Program
     * reference (no FK, ADR-0012) or a Faculty with no assigned Dean — is not counted as
     * flagged. Returns how many applications reached at least one Dean, so the count means
     * the same as the screening one (applications actually flagged to a reviewer).
     */
    private function flagDeanReviewOverdue(Notifier $notifier, DeanAssignments $assignments, ProgramCatalog $catalog): int
    {
        $overdue = $this->overdueIn(
            ApplicationStatus::UnderDeanReview,
            (int) config('admissions.dean_review_sla_days'),
        );

        $flagged = 0;

        foreach ($overdue as [$application, $ageDays]) {
            $programId = $application->primary_program_id;
            $summary = $programId === null ? null : $catalog->find($programId);

            if ($summary === null) {
                continue;
            }

            $deanIds = $assignments->deansForFaculty($summary->facultyId);

            foreach ($deanIds as $deanId) {
                $this->notifyOverdue($notifier, $deanId, $application, 'dean_review', $ageDays);
            }

            if ($deanIds !== []) {
                $flagged++;
            }
        }

        return $flagged;
    }

    /**
     * Raise one overdue notification to a reviewer about an application waiting past its
     * SLA. The payload names the application, the review stage it is stuck in
     * (`sa_screening`/`dean_review`), and its derived business-day age — one shape shared
     * by both stages so the two never drift apart.
     */
    private function notifyOverdue(Notifier $notifier, int $reviewerId, Application $application, string $stage, int $ageDays): void
    {
        $notifier->notify($reviewerId, NotificationType::ApplicationOverdue, [
            'application_id' => $application->getKey(),
            'stage' => $stage,
            'age_days' => $ageDays,
        ]);
    }

    /**
     * The applications resting in `$status` whose business-day age since entering it
     * exceeds `$slaDays`, each paired with that age. Aging is derived from the loaded
     * timeline (never stored), so the relation is eager-loaded.
     *
     * @return list<array{0: Application, 1: int}>
     */
    private function overdueIn(ApplicationStatus $status, int $slaDays): array
    {
        $applications = Application::query()
            ->with('statusTransitions')
            ->where('status', $status)
            ->orderBy('id')
            ->get();

        $overdue = [];

        foreach ($applications as $application) {
            $enteredAt = $application->enteredStatusAt($status);

            if ($enteredAt === null) {
                continue;
            }

            $ageDays = BusinessDays::between($enteredAt, now());

            if ($ageDays > $slaDays) {
                $overdue[] = [$application, $ageDays];
            }
        }

        return $overdue;
    }
}
