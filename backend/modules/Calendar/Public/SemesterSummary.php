<?php

namespace Modules\Calendar\Public;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Modules\Calendar\Internal\Support\SemesterCalendar;
use Modules\Calendar\Public\Enums\Term;

/**
 * A read-only view of a Semester for consumers outside Calendar (ADR-0012, ADR-0018).
 * This is the *only* shape in which Semester data crosses the module seam: the
 * Eloquent model never leaves `Calendar\Internal`, so a consumer gets exactly these
 * fields plus the period answers, and none of Calendar's write surface.
 *
 * The period questions (`isRegistrationOpen`, `isInAddDropPeriod`, …) delegate to the
 * one pure authority — {@see SemesterCalendar} — the same logic the model's derived
 * attributes and the CRUD validation use, so a loaded model and this facade DTO can
 * never disagree. Phase-3 registration, offerings, and attendance are the first
 * consumers; each reads the period it gates on from here.
 *
 * An immutable value object — the facade builds it, callers only read it.
 */
final readonly class SemesterSummary
{
    public function __construct(
        public int $id,
        public Term $term,
        public int $year,
        public string $nameEn,
        public string $nameAr,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public CarbonImmutable $registrationOpensAt,
        public CarbonImmutable $registrationClosesAt,
        public CarbonImmutable $addDropEndsAt,
        public CarbonImmutable $lateAddEndsAt,
        public CarbonImmutable $withdrawalDeadlineAt,
        public CarbonImmutable $gradeSubmissionDeadlineAt,
    ) {}

    /** Whether registration is open at the given moment. */
    public function isRegistrationOpen(CarbonInterface $now): bool
    {
        return $this->calendar()->isRegistrationOpen($now);
    }

    /** Whether the term is running at the given moment (start ≤ now ≤ end). */
    public function isRunning(CarbonInterface $now): bool
    {
        return $this->calendar()->isRunning($now);
    }

    /** Whether the semester has ended by the given moment. */
    public function hasEnded(CarbonInterface $now): bool
    {
        return $this->calendar()->hasEnded($now);
    }

    /**
     * Whether the grade-submission deadline has passed by the given moment (phase 4 ticket 09).
     * Grading is the consumer: its late-Sheet flagging command asks this of each Offering's
     * Semester. It gates nothing — an Instructor past the deadline is nudged, never locked out.
     */
    public function hasGradeSubmissionDeadlinePassed(CarbonInterface $now): bool
    {
        return $this->calendar()->hasGradeSubmissionDeadlinePassed($now);
    }

    /** Whether the free add/drop period (FR-REG-004 weeks 1–2) is active. */
    public function isInAddDropPeriod(CarbonInterface $now): bool
    {
        return $this->calendar()->isInAddDropPeriod($now);
    }

    /** Whether the add-only late-add period (FR-REG-004 week 3) is active. */
    public function isInLateAddPeriod(CarbonInterface $now): bool
    {
        return $this->calendar()->isInLateAddPeriod($now);
    }

    /** Whether the self-serve withdrawal period (status → W) is active. */
    public function isInWithdrawalPeriod(CarbonInterface $now): bool
    {
        return $this->calendar()->isInWithdrawalPeriod($now);
    }

    /**
     * The Semester's current period label — one of `upcoming` / `registration` /
     * `add_drop` / `late_add` / `withdrawal` / `closed` (ticket 07). The single home for
     * the phase vocabulary, so a consumer never re-derives the precedence (ADR-0018).
     */
    public function currentPeriod(CarbonInterface $now): string
    {
        return $this->calendar()->currentPeriod($now);
    }

    /**
     * When the period {@see self::currentPeriod()} reports closes, or null when the current period
     * is not a window that closes (`upcoming`/`closed`) — the deadline a consumer shows beside the
     * period name. Phase 4.5's Dashboard Cards read it from here rather than picking the matching
     * date out of the eight above themselves, so no consumer re-derives which window is live.
     */
    public function currentPeriodEndsAt(CarbonInterface $now): ?CarbonInterface
    {
        return $this->calendar()->currentPeriodEndsAt($now);
    }

    /** @return list<string> */
    public function openWindows(CarbonInterface $now): array
    {
        return $this->calendar()->openWindows($now);
    }

    /** @return ?array{key: string, at: CarbonInterface} */
    public function nextDeadline(CarbonInterface $now): ?array
    {
        return $this->calendar()->nextDeadline($now);
    }

    private function calendar(): SemesterCalendar
    {
        return new SemesterCalendar(
            startsAt: $this->startsAt,
            endsAt: $this->endsAt,
            registrationOpensAt: $this->registrationOpensAt,
            registrationClosesAt: $this->registrationClosesAt,
            addDropEndsAt: $this->addDropEndsAt,
            lateAddEndsAt: $this->lateAddEndsAt,
            withdrawalDeadlineAt: $this->withdrawalDeadlineAt,
            gradeSubmissionDeadlineAt: $this->gradeSubmissionDeadlineAt,
        );
    }
}
