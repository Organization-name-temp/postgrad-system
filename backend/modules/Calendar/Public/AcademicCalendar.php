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
}
