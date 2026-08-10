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
 * At the **cart submission** (ticket 05) the Student has no Enrollments yet, so the
 * caller passes {@see ExistingEnrollments::none()} and only the within-cart rules apply.
 * At the **advisor mint** (ticket 06) the caller passes the Student's record, and the same
 * seam additionally checks the caps, the duplicate and the clash **against existing
 * Enrollments** — the {@see MeetingTime} overlap primitive simply extends to the record.
 * Retake derivation (and its cap of one) is the {@see AttemptDeriver}'s job, done by the
 * caller alongside this. Eligibility (an Offering the Student may not take at all) is
 * enforced by the controller before a line is built.
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
    public function validate(array $lines, CreditHoursExceptions $exceptions, ?ExistingEnrollments $existing = null): array
    {
        $existing ??= ExistingEnrollments::none();

        return [
            ...$this->creditHourViolations($lines, $exceptions, $existing),
            ...$this->duplicateViolations($lines, $existing),
            ...$this->capViolations($lines, $existing),
            ...$this->outstandingIncompleteViolations($lines, $existing),
            ...$this->capacityViolations($lines),
            ...$this->clashViolations($lines, $existing),
        ];
    }

    /**
     * The floor/ceiling outcome, delegated to the shared {@see CreditHoursValidator}. The
     * Student's existing active load for the Semester ({@see ExistingEnrollments::$activeCreditHours})
     * is added in, so a free add or late-add is measured against the whole schedule, not
     * just the new lines; it is 0 for an initial cart, leaving that path unchanged.
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function creditHourViolations(array $lines, CreditHoursExceptions $exceptions, ExistingEnrollments $existing): array
    {
        $result = $this->creditHours->validate(
            array_map(static fn (CartLine $line): int => $line->totalCh, $lines),
            $exceptions,
            $existing->activeCreditHours,
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
     * Duplicate courses: one violation per course appearing on more than one line (a course
     * can be registered only once per semester, so Regular + Audit of it is a duplicate),
     * plus — at the mint — one per line for an Offering the Student is **already enrolled
     * in** (the "one Enrollment per (student, course, semester)" rule against the record).
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function duplicateViolations(array $lines, ExistingEnrollments $existing): array
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

        foreach ($lines as $line) {
            if (in_array($line->offeringId, $existing->enrolledOfferingIds, true)) {
                $violations[] = new CartViolation(
                    CartViolation::DUPLICATE_ENROLLMENT,
                    "{$line->courseCode} is already on the student's schedule this semester.",
                    [$line->offeringId],
                );
            }
        }

        return $violations;
    }

    /**
     * The composition caps, counting the Student's **record plus this cart**: at most one
     * External Elective ever (the one-allowance rule, BR-24) and at most one Research
     * Course (BR-25). At submission the record is empty, so this is a pure within-cart
     * cap; at the mint the existing active Enrollments in each classification are added in,
     * so a second one is blocked even when the first is already on the record (user
     * story 28).
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function capViolations(array $lines, ExistingEnrollments $existing): array
    {
        $violations = [];

        $external = $this->linesClassified($lines, EnrollmentClassification::ExternalElective);
        if (count($external) + $existing->countFor(EnrollmentClassification::ExternalElective) > 1) {
            $violations[] = new CartViolation(
                CartViolation::EXTERNAL_ELECTIVE_CAP,
                'Only one external elective is allowed per registration.',
                $this->offeringIds($external),
            );
        }

        $research = $this->linesClassified($lines, EnrollmentClassification::ResearchCourse);
        if (count($research) + $existing->countFor(EnrollmentClassification::ResearchCourse) > 1) {
            $violations[] = new CartViolation(
                CartViolation::RESEARCH_COURSE_CAP,
                'Only one research course is allowed per registration.',
                $this->offeringIds($research),
            );
        }

        return $violations;
    }

    /**
     * Re-registering a Course whose earlier Attempt is still unresolved Incomplete is illegal:
     * the existing Enrollment must be resolved, not bypassed with a fresh one.
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function outstandingIncompleteViolations(array $lines, ExistingEnrollments $existing): array
    {
        $violations = [];

        foreach ($lines as $line) {
            if ($existing->hasOutstandingIncompleteFor($line->courseId)) {
                $violations[] = new CartViolation(
                    CartViolation::OUTSTANDING_INCOMPLETE,
                    "{$line->courseCode} still has an unresolved Incomplete and cannot be registered again yet.",
                    [$line->offeringId],
                );
            }
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
     * Time clashes: one violation per clashing pair of chosen Offerings — any meeting of
     * one overlapping any meeting of the other on the same weekday — plus, at the mint,
     * one per line clashing with the Student's **existing schedule** for the target
     * Semester. All via the shared {@see MeetingTime} overlap seam (the same primitive the
     * offering self-overlap check uses).
     *
     * @param  list<CartLine>  $lines
     * @return list<CartViolation>
     */
    private function clashViolations(array $lines, ExistingEnrollments $existing): array
    {
        $violations = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if ($this->clash($lines[$i]->meetings, $lines[$j]->meetings)) {
                    $violations[] = new CartViolation(
                        CartViolation::TIME_CLASH,
                        "{$lines[$i]->courseCode} and {$lines[$j]->courseCode} meet at overlapping times.",
                        [$lines[$i]->offeringId, $lines[$j]->offeringId],
                    );
                }
            }
        }

        foreach ($lines as $line) {
            if ($this->clash($line->meetings, $existing->meetings)) {
                $violations[] = new CartViolation(
                    CartViolation::TIME_CLASH,
                    "{$line->courseCode} clashes with the student's existing schedule this semester.",
                    [$line->offeringId],
                );
            }
        }

        return $violations;
    }

    /**
     * Whether any slot in one set overlaps any slot in the other.
     *
     * @param  list<MeetingTime>  $a
     * @param  list<MeetingTime>  $b
     */
    private function clash(array $a, array $b): bool
    {
        foreach ($a as $slotA) {
            foreach ($b as $slotB) {
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
