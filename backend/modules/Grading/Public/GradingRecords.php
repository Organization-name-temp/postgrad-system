<?php

namespace Modules\Grading\Public;

use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Grading\GradingServiceProvider;

/**
 * The read facade other modules use to resolve a Student's GPA/CGPA and their Grades
 * (ADR-0012, ADR-0020, ticket 04) — the contract Phase 5's Academic Standing and Phase 6's
 * Transcript both consume, so the credit-weighted arithmetic is applied in exactly one place
 * and neither Phase re-derives it.
 *
 * Every figure is computed **on demand and never stored or cached** (ROADMAP §1 — no Redis
 * in this build), reading `Enrollment\Public`'s roster and `Calendar\Public`'s Semester dates
 * through the one-way `Grading → Enrollment`/`Grading → Calendar` seam (ADR-0022). The
 * concrete implementation lives in `Grading\Internal` and is bound in
 * {@see GradingServiceProvider}.
 */
interface GradingRecords
{
    /**
     * The chronologically latest finalized CGPA-bearing Attempt for each Course.
     *
     * @return list<EffectiveAttemptSummary>
     */
    public function effectiveAttemptsForStudent(int $studentId): array;

    /**
     * The Student's CGPA: `Σ(GPA points × credit hours) ÷ Σ(credit hours)` over their
     * CGPA-counting, non-superseded, graded Enrollments (spec §GPA and CGPA). `null` — never
     * `0.00` — when the Student has no CGPA-counting graded Enrollment yet, so a new record
     * never reads as a catastrophic one.
     */
    public function cgpaFor(int $studentId): ?float;

    /**
     * The same formula restricted to one Semester's Enrollments, from the same
     * non-superseded eligible set the CGPA is drawn from — a Retake that supersedes an
     * earlier Attempt is excluded from that earlier Attempt's own Semester GPA too, not only
     * from the CGPA. `null` when the Semester has no counted Enrollment for this Student,
     * including when every Attempt in it was later superseded.
     */
    public function semesterGpaFor(int $studentId, int $semesterId): ?float;

    /**
     * The current Grade for each of the given Enrollment ids, keyed by id — the one on its
     * latest Finalized Sheet (ADR-0023's supersession rule), never a Draft/Submitted mark not
     * yet part of the academic record. Ids with no Finalized Grade (ungraded, or gradable but
     * not yet finalized) are simply absent from the result, the {@see EnrollmentRecords::gradableCountsFor()}
     * `findMany` shape.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, GradeSummary>
     */
    public function gradesFor(array $enrollmentIds): array;
}
