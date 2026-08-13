<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Enrollment\Public\OfferingSummary;
use Modules\Grading\Internal\Enums\GradeSheetKind;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\GradeSheet;

/**
 * The My Grade Sheets Dashboard Card's payload (phase 4.5, ticket 05): the acting user's teaching
 * load for their current term, how much of it is **not yet Finalized**, and which Offerings are
 * past their Grade-Submission Deadline with nothing finalized.
 *
 * "Their current term" is derived here from the Offerings themselves — the **latest-starting
 * Semester they teach in that has already started** — rather than from the running Semester,
 * because grades are keyed in *after* a term ends, when no Semester is running and an Instructor
 * still owes the marks for the one that just finished. It is one rule with no branch, and it reads
 * only the Semester's own dates (ADR-0018), deriving nothing new.
 *
 * The overdue flag is a **nudge, not a gate**: past the deadline an Offering with no Finalized
 * Sheet is flagged and nothing is blocked or changed (phase 4 ticket 09). This Card is a second
 * surface for that existing flag and reads it from the same
 * {@see SemesterSummary::hasGradeSubmissionDeadlinePassed()} predicate, so it cannot acquire an
 * opinion the domain does not have.
 */
final readonly class GradeSheetsCardBuilder
{
    /** How many Offering rows the Card carries — the shared three-row Card convention. */
    private const ROW_LIMIT = 3;

    public function __construct(
        private CourseCatalog $courses,
        private AcademicCalendar $calendar,
    ) {}

    /**
     * @param  list<OfferingSummary>  $offerings  every Offering the acting user may grade — their
     *                                            own, or, for a central-entry user, all of them
     *                                            (the same two lists the Grade Sheets screen shows)
     * @return array{
     *     semester: ?array{id: int, term: string, year: int, name_en: string, name_ar: string},
     *     grade_submission_deadline_at: ?string,
     *     offering_count: int,
     *     unfinalized_count: int,
     *     overdue_count: int,
     *     offerings: list<array<string, mixed>>,
     * }
     */
    public function build(array $offerings): array
    {
        $semesters = $this->calendar->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->semesterId,
            $offerings,
        ))));

        $semester = $this->currentTeachingTerm($offerings, $semesters);

        if ($semester === null) {
            return [
                'semester' => null,
                'grade_submission_deadline_at' => null,
                'offering_count' => 0,
                'unfinalized_count' => 0,
                'overdue_count' => 0,
                'offerings' => [],
            ];
        }

        $current = array_values(array_filter(
            $offerings,
            fn (OfferingSummary $offering): bool => $offering->semesterId === $semester->id,
        ));

        $deadlinePassed = $semester->hasGradeSubmissionDeadlinePassed(now());
        $statuses = $this->initialSheetStatuses($current);
        $resolvedCourses = $this->courses->findMany(array_values(array_unique(array_map(
            fn (OfferingSummary $offering): int => $offering->courseId,
            $current,
        ))));

        $rows = [];
        $unfinalized = 0;
        foreach ($current as $offering) {
            $status = $statuses[$offering->id] ?? null;
            $isFinalized = $status === GradeSheetStatus::Finalized;

            if (! $isFinalized) {
                $unfinalized++;
            }

            $rows[] = [
                'offering_id' => $offering->id,
                'sheet_status' => $status?->value,
                // Flagged, never blocked: the deadline has passed and this Offering has no
                // Finalized Sheet. Exactly the condition the existing late-Sheet command flags.
                'is_overdue' => $deadlinePassed && ! $isFinalized,
                'course' => ($resolvedCourses[$offering->courseId] ?? null)?->toArray(),
            ];
        }

        return [
            // {@see SemesterPayload} rather than a literal: every Grading surface that mentions a
            // Semester must name it identically, or this Card and the Grade Sheets screen it links
            // into would disagree about a Semester's shape.
            'semester' => SemesterPayload::from($semester),
            'grade_submission_deadline_at' => $semester->gradeSubmissionDeadlineAt->toIso8601String(),
            'offering_count' => count($current),
            'unfinalized_count' => $unfinalized,
            'overdue_count' => $deadlinePassed ? $unfinalized : 0,
            // The counts above are over the whole term; the rows are the first few, so a heavy
            // teaching load stays a Card rather than becoming a list.
            'offerings' => array_slice($rows, 0, self::ROW_LIMIT),
        ];
    }

    /**
     * The Semester the Card is about: the latest-starting one among `$offerings` whose term has
     * already begun. Null when the user teaches nothing, or only in terms still ahead — both of
     * which render the Card's empty state rather than an error.
     *
     * @param  list<OfferingSummary>  $offerings
     * @param  array<int, SemesterSummary>  $semesters
     */
    private function currentTeachingTerm(array $offerings, array $semesters): ?SemesterSummary
    {
        $now = now();
        $current = null;

        foreach ($offerings as $offering) {
            $semester = $semesters[$offering->semesterId] ?? null;

            if ($semester === null || $semester->startsAt->greaterThan($now)) {
                continue;
            }

            if ($current === null || $semester->startsAt->greaterThan($current->startsAt)) {
                $current = $semester;
            }
        }

        return $current;
    }

    /**
     * Each Offering's Initial-round status, keyed by Offering id — the same round the Grade Sheets
     * screen reports, read from Grading's own table in one query rather than one per Offering.
     *
     * @param  list<OfferingSummary>  $offerings
     * @return array<int, GradeSheetStatus>
     */
    private function initialSheetStatuses(array $offerings): array
    {
        $offeringIds = array_map(fn (OfferingSummary $offering): int => $offering->id, $offerings);

        if ($offeringIds === []) {
            return [];
        }

        return GradeSheet::query()
            ->where('kind', GradeSheetKind::Initial)
            ->whereIn('course_offering_id', $offeringIds)
            ->get()
            ->mapWithKeys(fn (GradeSheet $sheet): array => [
                (int) $sheet->course_offering_id => $sheet->status,
            ])
            ->all();
    }
}
