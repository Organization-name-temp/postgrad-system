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
     * Every Course Offering that has ever run, most recent Semester first — the same ordering
     * {@see self::offeringsTaughtBy()} returns, because they populate the same screen. This is
     * the *central* "which Offerings exist" question, not a scoped one: Grading's Student
     * Affairs entry path (phase 4 ticket 09) needs an Offering picker over all of them, since a
     * records office keys grades in for Offerings it does not teach. Callers must therefore do
     * their own authorising — this contract answers what exists, never who may see it.
     *
     * @return list<OfferingSummary>
     */
    public function allOfferings(): array;

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

    /**
     * The summary of the Enrollment with the given id, or `null` when no such Enrollment
     * exists — unlike {@see self::gradableEnrollmentsOf()}, this is **not** filtered by
     * gradability, because a caller resolving a Grade's `enrollment_id` (ticket 03's
     * finalize notification, ticket 05's Incomplete resolution) must reach it even while its
     * Course Status is Incomplete or otherwise not a gradable line.
     */
    public function findEnrollment(int $enrollmentId): ?EnrollmentSummary;

    /**
     * The summaries of several Enrollments at once, keyed by id; ids that resolve to nothing are
     * simply absent from the result. Unfiltered by gradability for the same reason
     * {@see self::findEnrollment()} is — the `findMany` shape the Catalog facades already use,
     * added (phase 4.5, ticket 08) so a caller holding a set of `enrollment_id`s resolves them in
     * one read rather than in a loop.
     *
     * The caller that forced it is the Outstanding Incompletes Dashboard Card: an Incomplete
     * carries no Offering column, so "is this one mine?" can only be answered through its
     * Enrollment, and asking one at a time put an N+1 on the landing page of every user holding
     * `grades.enter`.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, EnrollmentSummary>
     */
    public function findManyEnrollments(array $enrollmentIds): array;

    /**
     * Every Enrollment the given Student has, in every Course Status, ordered by id — the
     * CGPA tally's whole input (ticket 04, spec §GPA and CGPA): Grading groups these by
     * {@see EnrollmentSummary::$courseId} for supersession and by
     * {@see EnrollmentSummary::$semesterId} for a Semester's GPA, and reads
     * {@see EnrollmentSummary::$countsTowardCgpa} rather than re-deriving Appendix D. Empty for
     * a Student with no Enrollments at all (the null-CGPA case) and for a student id that does
     * not exist.
     *
     * @return list<EnrollmentSummary>
     */
    public function enrollmentsForStudent(int $studentId): array;

    /**
     * Write the single `earned` bit onto an Enrollment — "this Attempt reached C+ and earned
     * its credit hours" (ADR-0022). The **only** writer of this column: called by Grading from
     * inside the same transaction as a Sheet's finalize, never on its own. `$earned` is
     * nullable so a resolution or correction can restore it to `null` (pending) as well as set
     * it true/false; Enrollment stores it verbatim and interprets nothing.
     */
    public function recordOutcome(int $enrollmentId, ?bool $earned): void;

    /**
     * Declare an Enrollment Incomplete (ticket 05, spec §Incomplete and Fail Withdrawal):
     * privately remembers the Course Status the Enrollment held before — a Retake or CGPA
     * Improvement, never assumed to be Regular — so {@see self::resolveIncomplete()} can
     * restore it, then sets the status to `Incomplete`. Called by Grading from inside the same
     * transaction as an **Initial** Sheet's finalize, never on its own; `CourseStatus` stays
     * `Enrollment\Internal`, so this is the only way another module reaches it.
     */
    public function markIncomplete(int $enrollmentId): void;

    /**
     * Resolve an Incomplete: restores the Course Status the Enrollment held before it became
     * Incomplete — a Retake comes back a Retake, never a Regular — and writes the `earned` bit
     * for the resolving Grade, atomically with the restore. Called by Grading from inside the
     * same transaction as an **IncompleteResolution** Sheet's finalize.
     */
    public function resolveIncomplete(int $enrollmentId, bool $earned): void;

    /**
     * Convert an unresolved Incomplete to Fail Withdrawal (SRS §4.7.3.3) — the **only** way an
     * Enrollment reaches `FailWithdrawal`: there is no administrative late-withdrawal path and
     * no Instructor-assigned FW. Called by the manually-run `grading:convert-expired-incompletes`
     * command, never anywhere else.
     */
    public function convertIncompleteToFailWithdrawal(int $enrollmentId): void;
}
