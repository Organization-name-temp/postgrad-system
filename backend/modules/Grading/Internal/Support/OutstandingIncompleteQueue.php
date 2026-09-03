<?php

namespace Modules\Grading\Internal\Support;

use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Http\Controllers\DashboardCardController;
use Modules\Grading\Internal\Http\Controllers\IncompleteController;
use Modules\Grading\Internal\Models\Incomplete;

/**
 * "Which outstanding Incompletes are mine?" — the one place that question is answered
 * (phase 4.5, ticket 08), shared by the resolution screen
 * ({@see IncompleteController::outstanding()}) and the Dashboard Card that summarises it
 * ({@see DashboardCardController::incompletes()}).
 *
 * An Incomplete carries no Offering column of its own, so the scope has to be resolved
 * through its Enrollment: every unresolved, unconverted declaration whose Enrollment sits on
 * an Offering the acting user teaches. Extracted rather than repeated because a Card that
 * counted a different set from the screen it links into would be worse than no Card — the same
 * discipline {@see DepartmentHeadSheetQueue} follows for the Department Head's review queue, and
 * Admissions' own `ReviewSla` for the screening SLA (named in prose, not linked: it lives in
 * `Admissions\Internal`, which Grading may not reference — ADR-0002).
 *
 * Read fresh on every call, and deliberately free of deadline arithmetic: the deadline is
 * derived per Enrollment Semester by {@see IncompleteDeadlineResolver} and never stored, so
 * each caller resolves it from the Semester ids this returns rather than being handed a
 * value that could be cached with the set.
 */
final readonly class OutstandingIncompleteQueue
{
    public function __construct(private EnrollmentRecords $enrollments) {}

    /**
     * The acting Instructor's outstanding Incompletes, oldest declaration first, with each
     * one's Enrollment resolved alongside it (keyed by Incomplete id) so a caller never asks
     * the facade for the same row twice. Empty — never an error — for a user who teaches
     * nothing, which is the correct answer for a central-entry user with no Offerings of
     * their own.
     *
     * @return array{incompletes: list<Incomplete>, enrollments: array<int, EnrollmentSummary>}
     */
    public function forInstructor(int $instructorUserId): array
    {
        $offeringIds = array_flip(array_map(
            static fn (OfferingSummary $offering): int => $offering->id,
            $this->enrollments->offeringsTaughtBy($instructorUserId),
        ));

        $outstanding = Incomplete::query()
            ->whereNull('resolved_grade_id')
            ->whereNull('converted_at')
            ->orderBy('id')
            ->get();

        // One facade read for the whole set, not one per Incomplete: this seam is on the Dashboard
        // page of every user holding `grades.enter`, so the loop that was acceptable on a screen
        // someone opens deliberately is not acceptable here.
        $byEnrollmentId = $this->enrollments->findManyEnrollments(
            $outstanding->pluck('enrollment_id')->map(fn ($id): int => (int) $id)->unique()->values()->all(),
        );

        $mine = [];
        $enrollments = [];

        foreach ($outstanding as $incomplete) {
            $enrollment = $byEnrollmentId[(int) $incomplete->enrollment_id] ?? null;

            if ($enrollment === null || ! isset($offeringIds[$enrollment->courseOfferingId])) {
                continue;
            }

            $mine[] = $incomplete;
            $enrollments[(int) $incomplete->getKey()] = $enrollment;
        }

        return ['incompletes' => $mine, 'enrollments' => $enrollments];
    }
}
