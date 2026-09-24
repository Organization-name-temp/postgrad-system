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

    /** An admission rule released one Preparatory requirement. */
    case AutomaticExemptionApplied = 'exemption.automatically_applied';

    /** A Student submitted or resubmitted a manual Exemption request. */
    case ManualExemptionSubmitted = 'exemption.request_submitted';

    /** A reviewer returned a manual Exemption request to the Student. */
    case ManualExemptionReturned = 'exemption.request_returned';

    /** An Advisor recommended a request to its scoped Department Head. */
    case ManualExemptionRecommended = 'exemption.request_recommended';

    /** A reviewer recorded a terminal manual Exemption outcome. */
    case ManualExemptionDecided = 'exemption.request_decided';

    /** A Student submitted or resubmitted a Transfer request. */
    case TransferRequestSubmitted = 'transfer.request_submitted';

    /** A reviewer returned a Transfer request for evidence revision. */
    case TransferRequestReturned = 'transfer.request_returned';

    /** A Transfer request advanced to its next reviewer. */
    case TransferRequestAdvanced = 'transfer.request_advanced';

    /** A reviewer recorded a terminal Transfer outcome. */
    case TransferRequestDecided = 'transfer.request_decided';

    case WaiverSubmitted = 'waiver.request_submitted';
    case WaiverReturned = 'waiver.request_returned';
    case WaiverRecommended = 'waiver.request_recommended';
    case WaiverVerified = 'waiver.request_verified';
    case WaiverDecided = 'waiver.request_decided';

    /** An effective Exemption, Transfer, or Waiver was revoked with a reason. */
    case CreditDecisionRevoked = 'credit_decision.revoked';

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

    /**
     * An advisee changed their own schedule during the free add/drop period (Phase 3,
     * ticket 07) — a direct action the Advisor is *notified* of, not asked to approve
     * (FR-REG-004, BR-70). Raised to the Student's live Advisor. Payload: `action`
     * (`added`/`dropped`), `course_code`, `student_name`.
     */
    case EnrollmentAdjusted = 'enrollment.adjusted';

    /**
     * An advisee withdrew from a course (Course Status → W) before the withdrawal
     * deadline (Phase 3, ticket 07) — self-serve, with no approval step, the Advisor kept
     * informed. Raised to the Student's live Advisor. Payload: `course_code`,
     * `student_name`.
     */
    case EnrollmentWithdrawn = 'enrollment.withdrawn';

    /**
     * An Audit Enrollment's derived attendance percentage first crossed below the audit
     * threshold (Appendix D's ≥75% rule; Phase 3, ticket 08, ADR-0019). Raised to the
     * Student once on the downward crossing — never on every mark, and never accompanied by
     * an automated status change. Payload: `course_code`, `percentage`.
     */
    case AttendanceAuditAtRisk = 'attendance.audit_at_risk';

    /**
     * An Instructor signed a Grade Sheet round (Phase 4, ticket 03) — raised to every
     * Department Head who reviews it (SRS §4.7.1.4). Payload: `course_offering_id`,
     * `grade_sheet_id`.
     */
    case GradeSheetSubmitted = 'grade.sheet_submitted';

    /**
     * A Department Head returned a Grade Sheet round with a note instead of finalizing it
     * (Phase 4, ticket 03) — raised to the Instructor. Payload: `grade_sheet_id`, `note`.
     */
    case GradeSheetReturned = 'grade.sheet_returned';

    /**
     * A Department Head finalized a Grade — it is now part of the academic record (Phase 4,
     * ticket 03). Raised to the Student, carrying the letter and whether it earned its credit
     * (< C+), so no second type is needed for the must-retake warning (ticket 07). Payload:
     * `enrollment_id`, `letter_grade`, `below_c_plus`.
     */
    case GradeFinalized = 'grade.finalized';

    /**
     * An Instructor's Incomplete declaration was finalized (Phase 4, ticket 05, SRS §4.7.3.2) —
     * raised to the Student the moment the Course Status actually becomes Incomplete (at the
     * Sheet's finalize, not the declaration), carrying the reason and the derived deadline so
     * it is never a silent decision. Payload: `enrollment_id`, `reason`, `deadline` (nullable —
     * absent exactly when the conversion command would also skip it).
     */
    case IncompleteDeclared = 'incomplete.declared';

    /**
     * A reminder that an outstanding Incomplete's resolution deadline is approaching (SRS
     * §4.7.3.4), raised by the manual `grading:send-incomplete-reminders` command on the exact
     * day the days-remaining figure matches one of the configured offsets. Payload:
     * `enrollment_id`, `deadline`, `days_remaining`.
     */
    case IncompleteReminder = 'incomplete.reminder';

    /**
     * An unresolved Incomplete passed its deadline and became a Fail Withdrawal (SRS §4.7.3.3)
     * — the one automated Course Status change in the system, raised by the manual
     * `grading:convert-expired-incompletes` command. Payload: `enrollment_id`.
     */
    case IncompleteConverted = 'incomplete.converted';

    /**
     * A Course Offering is past its Semester's grade-submission deadline with no Finalized Grade
     * Sheet (Phase 4, ticket 09) — raised by the manual `grading:flag-late-sheets` command to
     * the Instructor and the routed Department Head. Purely a nudge, the Phase-2 SLA-aging
     * pattern: the command changes no state and nothing is blocked by the deadline. Payload:
     * `course_offering_id`, `course_code`, `deadline`.
     */
    case GradesOverdue = 'grades.overdue';

    /** A real derived Academic Standing transition or recovery. */
    case StandingChanged = 'academic_standing.changed';

    /** An actionable termination Case opened, changed, or cleared. */
    case TerminationCaseChanged = 'academic_standing.termination_case_changed';
}
