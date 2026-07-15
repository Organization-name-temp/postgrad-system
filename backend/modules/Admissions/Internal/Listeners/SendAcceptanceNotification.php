<?php

namespace Modules\Admissions\Internal\Listeners;

use Modules\Admissions\Internal\Events\AdmissionAccepted;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * Sends the acceptance notification when an {@see AdmissionAccepted} event fires — the
 * one Phase-2 side-effect of the mint (ADR-0013). Registered against the event in
 * AdmissionsServiceProvider and resolved from the container (so the {@see Notifier}
 * facade is injected), it runs synchronously on dispatch.
 *
 * It carries the minted University ID into the payload so the SPA can render "you were
 * accepted, your University ID is …" bilingually from the {@see NotificationType} — no
 * text is stored (ticket 02).
 */
class SendAcceptanceNotification
{
    public function __construct(private readonly Notifier $notifier) {}

    public function handle(AdmissionAccepted $event): void
    {
        $this->notifier->notify($event->userId, NotificationType::ApplicationAccepted, [
            'application_id' => $event->applicationId,
            'university_id' => $event->universityId,
        ]);
    }
}
