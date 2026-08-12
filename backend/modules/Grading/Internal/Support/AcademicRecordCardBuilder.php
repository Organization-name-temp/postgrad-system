<?php

namespace Modules\Grading\Internal\Support;

/**
 * The My Academic Record Dashboard Card's payload (phase 4.5, ticket 01): the three numbers a
 * Student's landing page shows — CGPA, the latest Semester's GPA, and the credit hours actually
 * earned so far.
 *
 * It **reads** ADR-0020's on-demand derivation and derives nothing of its own, as ADR-0024
 * requires of every Card: the same single {@see StudentGpaTally::recordFor()} fetch My Grades
 * performs, the same {@see GpaCalculator::nonSuperseded()} eligible set, and
 * {@see GpaCalculator}'s own average / latest-Attempt / earned-hours answers. So there is
 * nothing here that could disagree with the full record the Card links into — a superseded
 * Retake is excluded from both, and an empty record reads `null` in both, never `0.00`.
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
        $latest = GpaCalculator::latest($eligible);

        $latestSemester = $latest === null ? null : ($record->semesters[$latest->semesterId] ?? null);
        $latestTerm = $latest === null ? [] : array_values(array_filter(
            $eligible,
            fn (GpaAttempt $attempt): bool => $attempt->semesterId === $latest->semesterId,
        ));

        return [
            'cgpa' => GpaCalculator::average($eligible),
            'latest_semester' => $latestSemester === null ? null : SemesterPayload::from($latestSemester),
            'latest_semester_gpa' => GpaCalculator::average($latestTerm),
            'earned_credit_hours' => GpaCalculator::earnedCreditHours($eligible),
        ];
    }
}
