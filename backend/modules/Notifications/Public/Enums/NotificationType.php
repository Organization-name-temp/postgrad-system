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

    /**
     * The Dean approved the application for interview (ticket 08). Distinct from
     * {@see self::InterviewScheduled}: the applicant is told an interview is coming,
     * before Student Affairs books the concrete slot (ticket 09). Payload:
     * `application_id`.
     */
    case ApprovedForInterview = 'application.approved_for_interview';

    /** An interview was scheduled. Payload: `scheduled_at`, `mode`, location/link. */
    case InterviewScheduled = 'interview.scheduled';

    /**
     * A reminder that a scheduled interview is coming up (ticket 11), raised by the
     * manual `admissions:send-interview-reminders` command to both the applicant and the
     * assigned interviewer. Payload: `scheduled_at`, `application_id`.
     */
    case InterviewReminder = 'interview.reminder';

    /**
     * An application has waited past its review SLA and needs a reviewer's attention
     * (ticket 11). Raised by the manual `admissions:flag-overdue` command to the
     * relevant reviewer — Student Affairs for screening, the Faculty's Dean(s) for Dean
     * review — never to the applicant; it changes no status. Payload: `application_id`,
     * `stage` (`sa_screening`/`dean_review`), `age_days`.
     */
    case ApplicationOverdue = 'application.overdue';

    /** A non-acceptance final decision. Payload: `decision`, optional `reason`. */
    case DecisionRecorded = 'decision.recorded';

    /** The applicant was accepted and became a Student. Payload: `university_id`. */
    case ApplicationAccepted = 'application.accepted';

    /**
     * A Student (or staff on their behalf) submitted a registration for the advisor's
     * review (Phase 3, ticket 06). Raised to the assigned Advisor so queue work finds
     * them. Payload: `registration_request_id`, `student_name`, `course_count`.
     */
    case RegistrationSubmitted = 'registration.submitted';

    /**
     * The Advisor decided a registration request (Phase 3, ticket 06). Raised to the
     * Student the moment their schedule exists (or the request is rejected). Payload:
     * `registration_request_id`, `outcome` (Approved/Rejected), `approved_count`,
     * `rejected_count`.
     */
    case RegistrationDecided = 'registration.decided';
}
