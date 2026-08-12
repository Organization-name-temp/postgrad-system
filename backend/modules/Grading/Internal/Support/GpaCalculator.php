<?php

namespace Modules\Grading\Internal\Support;

/**
 * The pure GPA/CGPA arithmetic (ADR-0021, spec §GPA and CGPA, ticket 04): closing SRS §4.7.2.
 * No database, no HTTP — every input arrives as {@see GpaAttempt}, the same discipline as
 * `Enrollment\Internal\Support\AttendanceCalculator`, so the formula, the supersession rule
 * and the rounding are all unit-testable directly.
 *
 * The two questions are answered by two separate static methods because a Student's CGPA and
 * one Semester's GPA are the **same weighted average over the same eligible set**, just
 * filtered differently (spec: "the semester GPA is the same sum restricted to one Semester") —
 * {@see self::nonSuperseded()} derives that eligible set once; a caller then filters it by
 * Semester before calling {@see self::average()} again. A superseded earlier Attempt is
 * therefore excluded from *its own original Semester's* GPA too, not only from the CGPA — it
 * stays visible on the record (the caller never drops the row), just outside every average.
 */
final class GpaCalculator
{
    /**
     * The non-superseded subset: one Attempt per Course, the chronologically **latest by
     * Semester `starts_at`** — never by (year, term), since Fall precedes the following
     * Spring (spec). "Latest" is unconditional: a worse Retake still wins over an earlier
     * better grade. Two different Courses never compete — grouping by course id is the whole
     * mechanism FR-REG-006.4's "all grades count" needs.
     *
     * @param  list<GpaAttempt>  $attempts  every CGPA-counting, graded Attempt (any Semester)
     * @return list<GpaAttempt>
     */
    public static function nonSuperseded(array $attempts): array
    {
        $latestByCourse = [];
        foreach ($attempts as $attempt) {
            $current = $latestByCourse[$attempt->courseId] ?? null;
            if ($current === null || $attempt->semesterStartsAt->greaterThan($current->semesterStartsAt)) {
                $latestByCourse[$attempt->courseId] = $attempt;
            }
        }

        return array_values($latestByCourse);
    }

    /**
     * The chronologically **latest** Attempt by Semester `starts_at` — the same ordering
     * {@see self::nonSuperseded()} supersedes by, and never (year, term), since Fall precedes
     * the following Spring. `null` for an empty set.
     *
     * Callers pass the already-eligible set, so a term whose every Attempt was later superseded
     * is never reported as the latest one.
     *
     * @param  list<GpaAttempt>  $attempts
     */
    public static function latest(array $attempts): ?GpaAttempt
    {
        $latest = null;
        foreach ($attempts as $attempt) {
            if ($latest === null || $attempt->semesterStartsAt->greaterThan($latest->semesterStartsAt)) {
                $latest = $attempt;
            }
        }

        return $latest;
    }

    /**
     * The credit hours these Attempts actually **earned** — the sum of `creditHours` over those
     * whose ADR-0022 `earned` bit is true. A grade below the C+ threshold counts toward the CGPA
     * but earns nothing, so this is never the same as the credit hours the average weights by.
     *
     * Callers pass the eligible (non-superseded) set: a superseded Attempt's credit hours were
     * already re-earned, or not, by the Attempt that superseded it, so counting both would
     * double the hours for one Course.
     *
     * @param  list<GpaAttempt>  $attempts
     */
    public static function earnedCreditHours(array $attempts): int
    {
        $hours = 0;
        foreach ($attempts as $attempt) {
            if ($attempt->earned === true) {
                $hours += $attempt->creditHours;
            }
        }

        return $hours;
    }

    /**
     * The credit-weighted average over the given Attempts: `Σ(GPA points × credit hours) ÷
     * Σ(credit hours)`, rounded **half-up to two decimals exactly once** so display and every
     * threshold comparison agree. `null` — never `0.00` — when the Attempts carry no credit
     * hours at all (a fresh record, or a Semester every one of whose Attempts was superseded).
     *
     * Precision: each Attempt's points (always one of nine exact values, ADR-0021) are
     * converted to integer hundredths before multiplying by its (integer) credit hours, so the
     * running sum is exact integer arithmetic with no float drift; the final division is
     * rounded with integer-only half-up rounding (`intdiv(2p + q, 2q)`), never
     * float `round()`, so a value on the 3.0 boundary can never land a hair to either side.
     *
     * @param  list<GpaAttempt>  $attempts  already the eligible set (non-superseded, optionally
     *                                      filtered to one Semester)
     */
    public static function average(array $attempts): ?float
    {
        $totalHours = 0;
        $totalPointHundredths = 0;

        foreach ($attempts as $attempt) {
            $totalHours += $attempt->creditHours;
            $totalPointHundredths += (int) round($attempt->gpaPoints * 100) * $attempt->creditHours;
        }

        if ($totalHours === 0) {
            return null;
        }

        $roundedHundredths = intdiv(2 * $totalPointHundredths + $totalHours, 2 * $totalHours);

        return $roundedHundredths / 100;
    }
}
