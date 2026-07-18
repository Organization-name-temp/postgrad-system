<?php

namespace Modules\Admissions\Internal\Enums;

use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Public\Enums\Permission;

/**
 * The lifecycle state of an Application — the closed set of 13 statuses from
 * SRS §4.2, in the same code-owned style as the Identity Role/Permission and
 * Admissions IntakeTerm enums (ADR-0008). Admissions-owned and Internal.
 *
 * Registration (ticket 03) starts every Application at {@see self::Draft}. The
 * hand-rolled transition machine — the allowed-transitions map ({@see self::allowedTransitions()}),
 * the named action methods on {@see Application},
 * and their business guards — is introduced by ticket 07, when the screening
 * workflow first moves an Application off Draft. This enum is defined in full up
 * front (the states are university bylaws, not incremental) so later tickets wire
 * behaviour onto a stable type rather than growing it.
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

    /**
     * The statuses this one may legally move to — the whole hand-rolled workflow,
     * in one static map (same style as {@see Permission::roles()}).
     * The named action methods on the Application model each perform one of these
     * moves; the model refuses anything not listed here with a 409. Some moves are
     * not wired to an action until a later ticket (Dean approve-for-interview and
     * the final decision arrive in tickets 08–10), but the map is complete up front
     * so illegal jumps are rejected uniformly from the start.
     *
     * `SAApproved` is transient — the approve action passes straight through it to
     * `UnderDeanReview`. `Accepted` and `Rejected` are terminal (no exits). The two
     * exits from `ConditionallyAccepted`/`Waitlisted` to `Rejected` are the extra
     * resolutions beyond the SRS diagram (spec §Status machine).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::EmailVerified],
            self::Submitted => [self::EmailVerified],
            self::EmailVerified => [self::UnderSAReview],
            self::UnderSAReview => [self::SAApproved, self::ReturnedForRevision, self::Rejected],
            self::SAApproved => [self::UnderDeanReview],
            self::ReturnedForRevision => [self::UnderSAReview],
            self::UnderDeanReview => [self::InterviewScheduled, self::Rejected],
            self::InterviewScheduled => [self::InterviewCompleted],
            self::InterviewCompleted => [
                self::Accepted,
                self::ConditionallyAccepted,
                self::Waitlisted,
                self::Rejected,
            ],
            self::ConditionallyAccepted => [self::Accepted, self::Rejected],
            self::Waitlisted => [self::Accepted, self::Rejected],
            self::Accepted, self::Rejected => [],
        };
    }

    /**
     * Whether moving from this status to `$to` is a legal transition. A status is
     * never legal into itself (self-moves are absent from every map entry).
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }
}
