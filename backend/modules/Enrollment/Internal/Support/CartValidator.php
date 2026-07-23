<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Enums\EnrollmentClassification;

/**
 * The pure batch validator for a registration cart (spec §Registration workflow,
 * FR-REG-001, BR-24/25) — everything computable at cart time, in one seam so the rule set
 * is expressed once and unit-tested without the HTTP stack or the database. Given the
 * resolved {@see CartLine}s and the {@see CreditHoursExceptions}, it returns the list of
 * {@see CartViolation}s (empty ⇒ the cart may be submitted):
 *
 * - **Credit-hour load** — the min/max window, delegated to {@see CreditHoursValidator}
 *   (floor waivable, ceiling always), counting every Regular + Audit line.
 * - **Composition caps** — at most one External Elective and at most one Research Course
 *   in the cart (the type-match for a Research Course is already enforced by the
 *   classifier — an unmatched course never becomes a Research Course line).
 * - **No duplicate course** — a course may appear on at most one line, so Regular + Audit
 *   of the same course is rejected.
 * - **Time clashes** — no two chosen Offerings may meet at overlapping weekly times,
 *   via the shared {@see MeetingTime} overlap seam.
 * - **Seat capacity** — a chosen Offering must have a seat remaining.
 *
 * Retake/duplicate-against-existing-Enrollment, and clashes against the Student's already
 * *minted* Enrollments, are *not* here — they need Enrollments, which do not exist until
 * the mint (ticket 06); the same {@see MeetingTime} seam extends to cover them there.
 * Eligibility (an Offering the Student may not take at all) is enforced by the controller
 * before a line is built.
 */
final readonly class CartValidator
{
    public function __construct(
        private CreditHoursValidator $creditHours,
    ) {}

    /**
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    public function validate(array $lines, CreditHoursExceptions $exceptions): array
    {
        return [
            ...$this->creditHourViolations($lines, $exceptions),
            ...$this->duplicateViolations($lines),
            ...$this->capViolations($lines),
            ...$this->capacityViolations($lines),
            ...$this->clashViolations($lines),
        ];
    }

    /**
     * The floor/ceiling outcome, delegated to the shared {@see CreditHoursValidator}.
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function creditHourViolations(array $lines, CreditHoursExceptions $exceptions): array
    {
        $result = $this->creditHours->validate(
            array_map(static fn (CartLine $line): int => $line->totalCh, $lines),
            $exceptions,
        );

        $offeringIds = $this->offeringIds($lines);
        $violations = [];

        if ($result->belowMinimum) {
            $violations[] = new CartViolation(
                CartViolation::BELOW_MIN_CREDIT_HOURS,
                "Your registration totals {$result->total} credit hours, below the {$this->creditHours->minCreditHours}-hour minimum.",
                $offeringIds,
            );
        }

        if ($result->aboveMaximum) {
            $violations[] = new CartViolation(
                CartViolation::ABOVE_MAX_CREDIT_HOURS,
                "Your registration totals {$result->total} credit hours, above the {$this->creditHours->maxCreditHours}-hour maximum.",
                $offeringIds,
            );
        }

        return $violations;
    }

    /**
     * One violation per course that appears on more than one line — a course can be
     * registered only once per semester, so Regular + Audit of it is a duplicate.
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function duplicateViolations(array $lines): array
    {
        $byCourse = [];
        foreach ($lines as $line) {
            $byCourse[$line->courseId][] = $line;
        }

        $violations = [];
        foreach ($byCourse as $courseLines) {
            if (count($courseLines) > 1) {
                $violations[] = new CartViolation(
                    CartViolation::DUPLICATE_COURSE,
                    "{$courseLines[0]->courseCode} is in your cart more than once; a course can be registered only once per semester.",
                    $this->offeringIds($courseLines),
                );
            }
        }

        return $violations;
    }

    /**
     * The composition caps: at most one External Elective (the one-allowance rule, BR-24)
     * and at most one Research Course (exactly one, BR-25) in a single cart.
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function capViolations(array $lines): array
    {
        $violations = [];

        $external = $this->linesClassified($lines, EnrollmentClassification::ExternalElective);
        if (count($external) > 1) {
            $violations[] = new CartViolation(
                CartViolation::EXTERNAL_ELECTIVE_CAP,
                'Only one external elective is allowed per registration.',
                $this->offeringIds($external),
            );
        }

        $research = $this->linesClassified($lines, EnrollmentClassification::ResearchCourse);
        if (count($research) > 1) {
            $violations[] = new CartViolation(
                CartViolation::RESEARCH_COURSE_CAP,
                'Only one research course is allowed per registration.',
                $this->offeringIds($research),
            );
        }

        return $violations;
    }

    /**
     * One violation per chosen Offering with no seat left (capped and full). Uncapped
     * Offerings (`seatsRemaining === null`) never violate.
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function capacityViolations(array $lines): array
    {
        $violations = [];
        foreach ($lines as $line) {
            if ($line->seatsRemaining !== null && $line->seatsRemaining < 1) {
                $violations[] = new CartViolation(
                    CartViolation::OFFERING_FULL,
                    "{$line->courseCode} is full — no seats remain.",
                    [$line->offeringId],
                );
            }
        }

        return $violations;
    }

    /**
     * One violation per clashing pair of Offerings — any meeting of one overlapping any
     * meeting of the other on the same weekday, via the shared {@see MeetingTime} seam
     * (the same primitive the offering self-overlap check uses).
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function clashViolations(array $lines): array
    {
        $violations = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->clash($lines[$i], $lines[$j])) {
                    $violations[] = new CartViolation(
                        CartViolation::TIME_CLASH,
                        "{$lines[$i]->courseCode} and {$lines[$j]->courseCode} meet at overlapping times.",
                        [$lines[$i]->offeringId, $lines[$j]->offeringId],
                    );
                }
            }
        }

        return $violations;
    }

    /** Whether any meeting of one line overlaps any meeting of the other. */
    private function clash(CartLine $a, CartLine $b): bool
    {
        foreach ($a->meetings as $slotA) {
            foreach ($b->meetings as $slotB) {
                if ($slotA->overlaps($slotB)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The cart lines carrying the given classification.
     *
     * @param  list<CartLine>  $lines
     * @return list<CartLine>
     */
    private function linesClassified(array $lines, EnrollmentClassification $classification): array
    {
        return array_values(array_filter(
            $lines,
            static fn (CartLine $line): bool => $line->classification === $classification,
        ));
    }

    /**
     * The offering ids of a set of cart lines — the shape a {@see CartViolation} carries to
     * name the offending lines.
     *
     * @param  list<CartLine>  $lines
     * @return list<int>
     */
    private function offeringIds(array $lines): array
    {
        return array_map(static fn (CartLine $line): int => $line->offeringId, $lines);
    }
}
