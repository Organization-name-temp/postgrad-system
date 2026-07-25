<?php

namespace Modules\Enrollment\Public;

use Modules\Enrollment\EnrollmentServiceProvider;
use Modules\Enrollment\Internal\Enums\CourseStatus;

/**
 * The read facade other modules use to resolve Course Offerings and Enrollments by id
 * (ADR-0012) — `Enrollment\Public`'s first real surface. ADR-0017 deferred it to "Phase 4,
 * built against grading", and ADR-0020 is the consumer that arrived: Grading owns the Grade
 * and the GPA read model, so it needs a roster to grade and an Offering's routing facts, and
 * must get them without touching Enrollment's tables.
 *
 * Everything crossing this seam is a DTO — {@see OfferingSummary} / {@see EnrollmentSummary},
 * never Enrollment's Eloquent models — and every cross-module reference on them (student,
 * course, semester, instructor) stays an **opaque id** with no foreign key, resolved by the
 * caller through that module's own facade. The concrete implementation lives in
 * `Enrollment\Internal` and is bound in {@see EnrollmentServiceProvider}.
 *
 * The dependency runs **one way, Grading → Enrollment** (ADR-0022): Enrollment never reads
 * Grading, so its registration gates stay pure functions over its own rows and there is no
 * `Public ↔ Public` cycle for deptrac to permit. The command surface Grading writes back
 * through (`recordOutcome` and the Incomplete transitions) joins this contract in the tickets
 * that first need it.
 */
interface EnrollmentRecords
{
    /**
     * The summary of the Course Offering with the given id, or `null` when no such Offering
     * exists. Callers must handle `null`: with no database foreign key backing the reference,
     * an id can dangle (in practice an Offering is undeletable once anything references it —
     * ADR-0017 — so the caller validates existence).
     */
    public function findOffering(int $offeringId): ?OfferingSummary;

    /**
     * Every Course Offering the given Instructor teaches, across all Semesters, most recent
     * Semester first. This is the "which courses are mine to grade" question — the Grade
     * Sheet list is scoped by it, exactly as the attendance register is. Empty when the user
     * teaches nothing (which is the correct answer for a non-Instructor, not an error).
     *
     * @return list<OfferingSummary>
     */
    public function offeringsTaughtBy(int $instructorUserId): array;

    /**
     * The **gradable** Enrollments on one Offering's roster, ordered by id. Gradability is
     * Enrollment's own judgement ({@see CourseStatus}), so
     * a consumer never re-derives it: an Audit or Withdrawn Enrollment is on the roster but is
     * never a gradable line, and an Instructor is therefore never asked for a mark that cannot
     * exist (ADR-0023). Empty for an Offering with no gradable roster, and for an id that does
     * not exist.
     *
     * @return list<EnrollmentSummary>
     */
    public function gradableEnrollmentsOf(int $offeringId): array;

    /**
     * How many gradable Enrollments each of the given Offerings has, keyed by Offering id;
     * Offerings with none are simply absent from the result. Lets a screen size several
     * rosters at once (the Grade Sheet list of everything an Instructor teaches) in one read
     * instead of N calls to {@see self::gradableEnrollmentsOf()} — the `findMany` shape the
     * Catalog facades already use.
     *
     * @param  list<int>  $offeringIds
     * @return array<int, int>
     */
    public function gradableCountsFor(array $offeringIds): array;
}
