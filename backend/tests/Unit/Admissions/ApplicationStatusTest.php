<?php

use Modules\Admissions\Internal\Enums\ApplicationStatus;

/*
| The hand-rolled Application status machine's allowed-transitions map (ticket 07).
| A pure unit over the enum — which moves are legal — the one piece of the workflow
| worth an authoritative direct test rather than an HTTP round-trip (spec §Testing
| Decisions, lower seam). The named action methods and their guards are exercised
| end-to-end in the screening feature tests; this pins the map itself.
*/

it('allows the applicant-driven early moves', function () {
    // Registration can go straight to email_verified (the pre-verified shortcut) or
    // through submitted first.
    expect(ApplicationStatus::Draft->canTransitionTo(ApplicationStatus::Submitted))->toBeTrue();
    expect(ApplicationStatus::Draft->canTransitionTo(ApplicationStatus::EmailVerified))->toBeTrue();
    expect(ApplicationStatus::Submitted->canTransitionTo(ApplicationStatus::EmailVerified))->toBeTrue();
    expect(ApplicationStatus::EmailVerified->canTransitionTo(ApplicationStatus::UnderSAReview))->toBeTrue();
});

it('allows Student Affairs screening to approve, return, or reject', function () {
    expect(ApplicationStatus::UnderSAReview->canTransitionTo(ApplicationStatus::SAApproved))->toBeTrue();
    expect(ApplicationStatus::UnderSAReview->canTransitionTo(ApplicationStatus::ReturnedForRevision))->toBeTrue();
    expect(ApplicationStatus::UnderSAReview->canTransitionTo(ApplicationStatus::Rejected))->toBeTrue();
});

it('auto-forwards an SA-approved application to Dean review', function () {
    expect(ApplicationStatus::SAApproved->canTransitionTo(ApplicationStatus::UnderDeanReview))->toBeTrue();
});

it('loops a returned application back into SA review on resubmit', function () {
    expect(ApplicationStatus::ReturnedForRevision->canTransitionTo(ApplicationStatus::UnderSAReview))->toBeTrue();
});

it('allows the later Dean, interview, and decision moves', function () {
    expect(ApplicationStatus::UnderDeanReview->canTransitionTo(ApplicationStatus::InterviewScheduled))->toBeTrue();
    expect(ApplicationStatus::UnderDeanReview->canTransitionTo(ApplicationStatus::Rejected))->toBeTrue();
    expect(ApplicationStatus::InterviewScheduled->canTransitionTo(ApplicationStatus::InterviewCompleted))->toBeTrue();

    foreach ([
        ApplicationStatus::Accepted,
        ApplicationStatus::ConditionallyAccepted,
        ApplicationStatus::Waitlisted,
        ApplicationStatus::Rejected,
    ] as $decision) {
        expect(ApplicationStatus::InterviewCompleted->canTransitionTo($decision))->toBeTrue();
    }
});

it('allows the extra conditional and waitlist exits to a real outcome', function () {
    // Extra exits beyond the SRS diagram (spec §Status machine).
    expect(ApplicationStatus::ConditionallyAccepted->canTransitionTo(ApplicationStatus::Accepted))->toBeTrue();
    expect(ApplicationStatus::ConditionallyAccepted->canTransitionTo(ApplicationStatus::Rejected))->toBeTrue();
    expect(ApplicationStatus::Waitlisted->canTransitionTo(ApplicationStatus::Accepted))->toBeTrue();
    expect(ApplicationStatus::Waitlisted->canTransitionTo(ApplicationStatus::Rejected))->toBeTrue();
});

it('refuses illegal jumps across the workflow', function () {
    // Skipping screening entirely.
    expect(ApplicationStatus::Draft->canTransitionTo(ApplicationStatus::UnderSAReview))->toBeFalse();
    expect(ApplicationStatus::EmailVerified->canTransitionTo(ApplicationStatus::SAApproved))->toBeFalse();
    // Approving something not under SA review.
    expect(ApplicationStatus::UnderDeanReview->canTransitionTo(ApplicationStatus::SAApproved))->toBeFalse();
    // Stepping backwards.
    expect(ApplicationStatus::UnderDeanReview->canTransitionTo(ApplicationStatus::UnderSAReview))->toBeFalse();
    // Deciding before the interview.
    expect(ApplicationStatus::UnderSAReview->canTransitionTo(ApplicationStatus::Accepted))->toBeFalse();
});

it('treats Accepted and Rejected as terminal', function () {
    expect(ApplicationStatus::Accepted->allowedTransitions())->toBe([]);
    expect(ApplicationStatus::Rejected->allowedTransitions())->toBe([]);

    // A status is never legal into itself.
    foreach (ApplicationStatus::cases() as $status) {
        expect($status->canTransitionTo($status))->toBeFalse();
    }
});
