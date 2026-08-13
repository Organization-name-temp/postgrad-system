<?php

namespace Modules\Calendar\Internal\Support;

use Carbon\CarbonInterface;
use Modules\Calendar\Public\SemesterSummary;

/**
 * The pure date logic of a Semester (ADR-0018, ticket 01): the cross-field
 * ordering invariant enforced on write, and the period answers derived from the
 * dates — never stored flags (the `AdmissionCycle::isOpen()` precedent).
 *
 * A stateless value with no clock of its own: the caller passes the eight period
 * dates once and asks its questions against an explicit `$now`. This is the single
 * authority for "is registration open", "is the term running", the add/drop /
 * late-add / withdrawal windows, and whether grades are late — the model's derived
 * attributes, the CRUD validation, and the {@see SemesterSummary} period
 * answers all delegate here so a query, a loaded model, and the cross-module facade
 * can never drift (ADR-0018 §Consequences). Unit-tested directly (spec §Testing).
 */
final class SemesterCalendar
{
    public function __construct(
        public readonly CarbonInterface $startsAt,
        public readonly CarbonInterface $endsAt,
        public readonly CarbonInterface $registrationOpensAt,
        public readonly CarbonInterface $registrationClosesAt,
        public readonly CarbonInterface $addDropEndsAt,
        public readonly CarbonInterface $lateAddEndsAt,
        public readonly CarbonInterface $withdrawalDeadlineAt,
        public readonly CarbonInterface $gradeSubmissionDeadlineAt,
    ) {}

    /**
     * The ordering rules that must hold, as [field => reason-key] for every rule
     * broken; an empty array means the calendar is well-ordered. The invariant
     * (ticket 01, ADR-0018): registration opens before it closes and before the
     * semester starts; registration closes no later than the add/drop deadline;
     * add/drop end < late-add end < withdrawal deadline ≤ semester end ≤ grade-submission
     * deadline; and the semester ends after it starts. Each broken rule is keyed to the
     * field an administrator would move to fix it, so the 422 reads naturally on the form.
     *
     * @return array<string, string>
     */
    public function orderingViolations(): array
    {
        $violations = [];

        if ($this->registrationOpensAt->greaterThanOrEqualTo($this->registrationClosesAt)
            || $this->registrationOpensAt->greaterThanOrEqualTo($this->startsAt)) {
            $violations['registration_opens_at'] = 'ordering.registration_opens_at';
        }

        if ($this->registrationClosesAt->greaterThan($this->addDropEndsAt)) {
            $violations['registration_closes_at'] = 'ordering.registration_closes_at';
        }

        if ($this->addDropEndsAt->greaterThanOrEqualTo($this->lateAddEndsAt)) {
            $violations['add_drop_ends_at'] = 'ordering.add_drop_ends_at';
        }

        if ($this->lateAddEndsAt->greaterThanOrEqualTo($this->withdrawalDeadlineAt)) {
            $violations['late_add_ends_at'] = 'ordering.late_add_ends_at';
        }

        if ($this->withdrawalDeadlineAt->greaterThan($this->endsAt)) {
            $violations['withdrawal_deadline_at'] = 'ordering.withdrawal_deadline_at';
        }

        // Phase 4 ticket 09: grades are submitted for a term that has finished, so the deadline
        // sits on or after the end — keyed to the deadline itself, the field an administrator
        // moves to fix it, exactly as the withdrawal rule above is.
        if ($this->gradeSubmissionDeadlineAt->lessThan($this->endsAt)) {
            $violations['grade_submission_deadline_at'] = 'ordering.grade_submission_deadline_at';
        }

        if ($this->endsAt->lessThanOrEqualTo($this->startsAt)) {
            $violations['ends_at'] = 'ordering.ends_at';
        }

        return $violations;
    }

    /** Whether the moment falls inside the registration window (both bounds inclusive). */
    public function isRegistrationOpen(CarbonInterface $now): bool
    {
        return $now->betweenIncluded($this->registrationOpensAt, $this->registrationClosesAt);
    }

    /** Whether the moment falls between the semester start and end (both bounds inclusive). */
    public function isRunning(CarbonInterface $now): bool
    {
        return $now->betweenIncluded($this->startsAt, $this->endsAt);
    }

    /** Whether the semester has ended (strictly after its end). */
    public function hasEnded(CarbonInterface $now): bool
    {
        return $now->greaterThan($this->endsAt);
    }

    /**
     * Whether the grade-submission deadline has passed (strictly after it, so the deadline day
     * itself is still in time — the Incomplete resolution window's inclusive reading). This
     * gates **nothing**: Phase 4 ticket 09 flags an Offering with no finalized Sheet past this
     * moment and notifies, the SLA-aging pattern, because an Instructor locked out of
     * submitting could not deliver grades at all.
     */
    public function hasGradeSubmissionDeadlinePassed(CarbonInterface $now): bool
    {
        return $now->greaterThan($this->gradeSubmissionDeadlineAt);
    }

    /**
     * The free add/drop period — from the semester start through the add/drop
     * deadline (FR-REG-004 weeks 1–2), both bounds inclusive.
     */
    public function isInAddDropPeriod(CarbonInterface $now): bool
    {
        return $now->betweenIncluded($this->startsAt, $this->addDropEndsAt);
    }

    /**
     * The late-add period — strictly after add/drop ends, through the late-add
     * deadline (FR-REG-004 week 3, add-only).
     */
    public function isInLateAddPeriod(CarbonInterface $now): bool
    {
        return $now->greaterThan($this->addDropEndsAt)
            && $now->lessThanOrEqualTo($this->lateAddEndsAt);
    }

    /**
     * The self-serve withdrawal period — strictly after late-add ends, through the
     * withdrawal deadline (FR-REG-004, status → W).
     */
    public function isInWithdrawalPeriod(CarbonInterface $now): bool
    {
        return $now->greaterThan($this->lateAddEndsAt)
            && $now->lessThanOrEqualTo($this->withdrawalDeadlineAt);
    }

    /**
     * The single label for "which period is this Semester in right now" — the authority
     * the schedule view reads to phrase what a Student may do (ticket 07). One of
     * `upcoming` / `registration` / `add_drop` / `late_add` / `withdrawal` / `closed`.
     *
     * The change periods take precedence over the registration window where they overlap
     * (registration may still be open past the semester start), so a running term reads as
     * its live change phase. Kept here — beside the predicates it composes — so the phase
     * vocabulary lives with the rest of the Semester's period logic, never re-derived by a
     * consumer (ADR-0018 §Consequences).
     */
    public function currentPeriod(CarbonInterface $now): string
    {
        return match (true) {
            $this->isInWithdrawalPeriod($now) => 'withdrawal',
            $this->isInLateAddPeriod($now) => 'late_add',
            $this->isInAddDropPeriod($now) => 'add_drop',
            $this->isRegistrationOpen($now) => 'registration',
            $now->lessThan($this->registrationOpensAt) => 'upcoming',
            default => 'closed',
        };
    }

    /**
     * When the period {@see self::currentPeriod()} reports **closes** — the deadline a Student or an
     * administrator is actually working against — or null when the current period is not a window
     * that closes: `upcoming` has not opened yet and `closed` has nothing left to close.
     *
     * Matched on the period label rather than re-testing the predicates, so it inherits the
     * precedence above for free and the two answers can never disagree about which window is live.
     * It lives here, beside the phase vocabulary, for the reason ADR-0018 gives: a consumer must
     * never re-derive a period answer, and phase 4.5's Dashboard Cards are the second and third
     * consumers to need this one.
     */
    public function currentPeriodEndsAt(CarbonInterface $now): ?CarbonInterface
    {
        return match ($this->currentPeriod($now)) {
            'withdrawal' => $this->withdrawalDeadlineAt,
            'late_add' => $this->lateAddEndsAt,
            'add_drop' => $this->addDropEndsAt,
            'registration' => $this->registrationClosesAt,
            default => null,
        };
    }
}
