<?php

namespace Modules\Grading\Internal\Support;

use Modules\Calendar\Public\SemesterSummary;

/**
 * The My Academic Record Dashboard Card's payload (phase 4.5, ticket 01): the three numbers a
 * Student's landing page shows — CGPA, the latest Semester's GPA, and the credit hours actually
 * earned so far.
 *
 * It **reads** ADR-0020's on-demand derivation rather than repeating it: the same single
 * {@see StudentGpaTally::recordFor()} fetch My Grades performs, the same
 * {@see GpaCalculator::nonSuperseded()} eligible set, and the same {@see GpaCalculator::average()}
 * arithmetic. A Card introduces no domain logic of its own (ADR-0024), so there is nothing here
 * to disagree with the full record the Card links into — a superseded Retake is excluded from
 * both, and an empty record reads `null` in both, never `0.00`.
 *
 * "Latest Semester" is the chronologically latest by Semester `starts_at` — never by (year,
 * term), since Fall precedes the following Spring — and it is drawn from the **eligible** set,
 * so a term whose every Attempt was later superseded is not the one reported.
 */
final readonly class AcademicRecordCardBuilder
{
    public function __construct(private StudentGpaTally $tally) {}

    /**
     * @return array{
     *     cgpa: ?float,
     *     latest_semester: ?array<string, mixed>,
     *     latest_semester_gpa: ?float,
     *     earned_credit_hours: int,
     * }
     */
    public function forStudent(int $studentId): array
    {
        $record = $this->tally->recordFor($studentId);
        $eligible = GpaCalculator::nonSuperseded($record->attempts);

        $earned = [];
        foreach ($record->enrollments as $enrollment) {
            $earned[$enrollment->id] = $enrollment->earned;
        }

        // ADR-0022's `earned` bit, summed over the eligible set only: a superseded Attempt's
        // credit hours were already re-earned (or not) by the Attempt that superseded it.
        $earnedCreditHours = 0;
        $latest = null;
        foreach ($eligible as $attempt) {
            if (($earned[$attempt->enrollmentId] ?? null) === true) {
                $earnedCreditHours += $attempt->creditHours;
            }

            if ($latest === null || $attempt->semesterStartsAt->greaterThan($latest->semesterStartsAt)) {
                $latest = $attempt;
            }
        }

        $latestSemester = $latest === null ? null : ($record->semesters[$latest->semesterId] ?? null);

        return [
            'cgpa' => GpaCalculator::average($eligible),
            'latest_semester' => $latestSemester === null ? null : $this->semesterArray($latestSemester),
            'latest_semester_gpa' => $latest === null ? null : GpaCalculator::average(array_values(array_filter(
                $eligible,
                fn (GpaAttempt $attempt): bool => $attempt->semesterId === $latest->semesterId,
            ))),
            'earned_credit_hours' => $earnedCreditHours,
        ];
    }

    /**
     * The Semester as domain data — id, term, year and both names — never a pre-rendered label,
     * so the SPA renders the Card bilingually from the payload (the Notifications rule). The
     * same five fields {@see StudentGradesRecordBuilder} sends, so the Card and the record it
     * links into name a Semester identically.
     *
     * @return array<string, mixed>
     */
    private function semesterArray(SemesterSummary $semester): array
    {
        return [
            'id' => $semester->id,
            'term' => $semester->term->value,
            'year' => $semester->year,
            'name_en' => $semester->nameEn,
            'name_ar' => $semester->nameAr,
        ];
    }
}
