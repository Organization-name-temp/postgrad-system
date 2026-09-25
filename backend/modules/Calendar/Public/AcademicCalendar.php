<?php

namespace Modules\Calendar\Public;

use Modules\Calendar\CalendarServiceProvider;

/**
 * The read facade other modules use to resolve the academic calendar (ADR-0018,
 * ADR-0012). It is the sanctioned Calendar\Public seam: callers store a Semester's
 * id as an opaque reference (no cross-module foreign key) and read the term and its
 * period windows back only through this contract, which returns
 * {@see SemesterSummary} DTOs — never Calendar's Eloquent model.
 *
 * The concrete implementation lives in `Calendar\Internal` and is bound in
 * {@see CalendarServiceProvider}. Phase-3 Enrollment is the first consumer:
 * offerings resolve their Semester by id; the registration/add-drop/late-add/
 * withdrawal gates read {@see self::openForRegistration()} and the period answers
 * on the returned summary.
 */
interface AcademicCalendar
{
    /**
     * The summary of the Semester with the given id, or `null` when no such Semester
     * exists. Callers must handle `null`: with no database foreign key backing the
     * reference, an id can dangle (in practice a Semester is never hard-deleted —
     * ADR-0018 — so the caller validates existence).
     */
    public function find(int $id): ?SemesterSummary;

    /**
     * The summaries for the given Semester ids, keyed by id; ids with no matching Semester are
     * simply absent from the result. Lets a screen or a command resolve several Semesters in one
     * read instead of N single lookups — the `CourseCatalog::findMany()` shape. Grading is the
     * first consumer: its Offering picker spans every Semester, and its late-Sheet flagging
     * command reads each Offering's Semester deadline.
     *
     * @param  list<int>  $ids
     * @return array<int, SemesterSummary>
     */
    public function findMany(array $ids): array;

    /** Resolve the stable English Semester name stored on the Student anchor. */
    public function findByNameEn(string $name): ?SemesterSummary;

    /**
     * The Semester whose registration window is open right now, or `null` when none
     * is — the front the student registration screen reads (user story 13). If more
     * than one qualifies (an operator error the ordering invariant does not prevent
     * across rows), the most recent by year is returned.
     */
    public function openForRegistration(): ?SemesterSummary;

    /**
     * The Semester running right now (start ≤ now ≤ end), or `null` when none is.
     * The most recent by year is returned if more than one qualifies.
     */
    public function running(): ?SemesterSummary;

    /**
     * The latest Semester that has begun, including a recently ended term whose
     * academic work (such as grade submission) is still being completed.
     */
    public function currentForAcademicWork(): ?SemesterSummary;

    /**
     * The Semester whose `starts_at` is the earliest one **after** the given Semester's own —
     * never by (year, term), since Fall precedes the following Spring (the same ordering rule
     * Grading's CGPA supersession follows). `null` when `$semesterId` does not exist, or when
     * it is the last Semester on the Calendar so far (an admin has not entered the next one
     * yet). Ticket 05's Incomplete deadline is derived from this Semester's `starts_at`, so a
     * later correction to it moves every outstanding deadline with it — nothing is frozen.
     */
    public function nextAfter(int $semesterId): ?SemesterSummary;
}
