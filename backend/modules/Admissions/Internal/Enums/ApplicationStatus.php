<?php

namespace Modules\Admissions\Internal\Enums;

/**
 * The lifecycle state of an Application — the closed set of 13 statuses from
 * SRS §4.2, in the same code-owned style as the Identity Role/Permission and
 * Admissions IntakeTerm enums (ADR-0008). Admissions-owned and Internal.
 *
 * Registration (ticket 03) starts every Application at {@see self::Draft}. The
 * hand-rolled transition machine — the allowed-transitions map, the named action
 * methods, and their business guards — is introduced by ticket 07, when the
 * screening/decision workflow first moves an Application off Draft. This enum is
 * defined in full up front (the states are university bylaws, not incremental)
 * so later tickets wire behaviour onto a stable type rather than growing it.
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case EmailVerified = 'email_verified';
    case UnderSAReview = 'under_sa_review';
    case SAApproved = 'sa_approved';
    case ReturnedForRevision = 'returned_for_revision';
    case UnderDeanReview = 'under_dean_review';
    case InterviewScheduled = 'interview_scheduled';
    case InterviewCompleted = 'interview_completed';
    case Accepted = 'accepted';
    case ConditionallyAccepted = 'conditionally_accepted';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';
}
