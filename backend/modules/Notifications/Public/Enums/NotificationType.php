<?php

namespace Modules\Notifications\Public\Enums;

use Modules\Notifications\Public\Contracts\Notifier;

/**
 * The kinds of in-app notification the system can raise. A closed set that lives
 * in code — the same style as the Identity Role/Permission and Admissions
 * IntakeTerm enums (ADR-0008) — and Notifications-owned. It sits in the module's
 * `Public` facade (not `Internal`) precisely because other modules name a case
 * when they call {@see Notifier::notify()};
 * the enum is the shared vocabulary of that contract.
 *
 * No human-readable text is stored against a notification: the SPA renders each
 * type bilingually from its `data` payload via the `notifications` i18n
 * namespace (ticket 02). The payload keys noted below are therefore illustrative
 * of what the renderer reads — they are not a schema the backend enforces.
 *
 * The set grows as later Phase-2 tickets wire the events that emit each type
 * (screening returns, scheduling, decisions, the acceptance mint, reminders).
 */
enum NotificationType: string
{
    /** An application was returned for revision. Payload: reviewer `note`. */
    case ApplicationReturned = 'application.returned';

    /** An interview was scheduled. Payload: `scheduled_at`, `mode`, location/link. */
    case InterviewScheduled = 'interview.scheduled';

    /** A reminder that a scheduled interview is coming up. Payload: `scheduled_at`. */
    case InterviewReminder = 'interview.reminder';

    /** A non-acceptance final decision. Payload: `decision`, optional `reason`. */
    case DecisionRecorded = 'decision.recorded';

    /** The applicant was accepted and became a Student. Payload: `university_id`. */
    case ApplicationAccepted = 'application.accepted';
}
