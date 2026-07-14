<?php

namespace Modules\Admissions\Internal\Http\Concerns;

use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Http\Controllers\ApplicationScreeningController;
use Modules\Admissions\Internal\Http\Controllers\DeanReviewController;
use Modules\Admissions\Internal\Models\Application;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * The applicant-facing notifications a reviewer action raises, so their payload shape
 * lives in one place rather than being re-inlined per reviewer surface. Shared by the
 * Student Affairs screening ({@see ApplicationScreeningController})
 * and Dean review ({@see DeanReviewController})
 * controllers: both reject to a recorded decision, and SA also returns for revision.
 * No text is stored — the SPA renders each {@see NotificationType} from its payload
 * (ticket 02).
 */
trait NotifiesApplicant
{
    /**
     * Notify the applicant that a non-acceptance decision was recorded on their
     * application — a rejection at any review stage (Student Affairs screening or Dean
     * review). `$reason` is the reviewer's stated reason.
     */
    protected function notifyDecisionRecorded(
        Notifier $notifier,
        Application $application,
        ApplicationStatus $decision,
        ?string $reason,
    ): void {
        $notifier->notify((int) $application->user_id, NotificationType::DecisionRecorded, [
            'application_id' => $application->getKey(),
            'decision' => $decision->value,
            'reason' => $reason,
        ]);
    }

    /**
     * Notify the applicant that their application was returned for revision, carrying
     * the reviewer's instructions.
     */
    protected function notifyReturnedForRevision(Notifier $notifier, Application $application, string $note): void
    {
        $notifier->notify((int) $application->user_id, NotificationType::ApplicationReturned, [
            'application_id' => $application->getKey(),
            'note' => $note,
        ]);
    }
}
