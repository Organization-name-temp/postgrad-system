<?php

namespace Modules\Enrollment\Internal\Http\Concerns;

use Modules\Enrollment\Internal\Enums\RegistrationStatus;
use Modules\Enrollment\Internal\Models\RegistrationRequest;
use Modules\Enrollment\Internal\Models\RegistrationRequestLine;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

/**
 * The registration notifications a submission or an advisor decision raises, so their
 * payload shape lives in one place rather than being re-inlined per surface (the Admissions
 * NotifiesApplicant precedent). No text is stored — the SPA renders each
 * {@see NotificationType} bilingually from its payload via the `notifications` i18n
 * namespace (Phase 2 mechanics). Both are fired as a side effect **after** the owning
 * transaction commits, so nobody is told of a fact that did not persist.
 */
trait NotifiesRegistration
{
    /**
     * Notify the assigned Advisor that a registration awaits their review — raised on the
     * Student's (or staff-on-behalf) submit path. `$advisorUserId` is the Student's live
     * advisor; `$studentName` is resolved through the Identity directory for a legible line.
     */
    protected function notifyRegistrationSubmitted(
        Notifier $notifier,
        int $advisorUserId,
        RegistrationRequest $registration,
        string $studentName,
        int $courseCount,
    ): void {
        $notifier->notify($advisorUserId, NotificationType::RegistrationSubmitted, [
            'registration_request_id' => $registration->getKey(),
            'student_name' => $studentName,
            'course_count' => $courseCount,
        ]);
    }

    /**
     * Notify the Student that their advisor decided the request — the outcome (Approved when
     * ≥1 line was kept, Rejected when all were trimmed), the per-line tally, and the per-line
     * results (spec line 14: "outcome + per-line results"), so they learn their schedule the
     * moment it exists. The `lines` results read the request's decided lines, so the request
     * must be passed with its lines loaded and their decisions stamped.
     */
    protected function notifyRegistrationDecided(
        Notifier $notifier,
        int $studentUserId,
        RegistrationRequest $registration,
        RegistrationStatus $outcome,
        int $approvedCount,
        int $rejectedCount,
    ): void {
        $notifier->notify($studentUserId, NotificationType::RegistrationDecided, [
            'registration_request_id' => $registration->getKey(),
            'outcome' => $outcome->value,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'lines' => $registration->lines->map(fn (RegistrationRequestLine $line): array => [
                'offering_id' => $line->course_offering_id,
                'decision' => $line->decision?->value,
            ])->all(),
        ]);
    }
}
