<?php

namespace Modules\Grading\Internal\Support;

use Modules\Grading\GradingServiceProvider;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradeSummary;
use Modules\Grading\Public\GradingRecords;

/**
 * The Eloquent-backed {@see GradingRecords} (ADR-0020, ticket 04). The GPA/CGPA arithmetic
 * itself lives in the pure {@see GpaCalculator}; this class only assembles its input through
 * {@see StudentGpaTally} and looks up Grades through {@see CurrentGradeLookup} — neither the
 * Grade model nor any Grading-internal type crosses to the caller (ADR-0012). Bound to the
 * interface in {@see GradingServiceProvider}.
 */
final readonly class EloquentGradingRecords implements GradingRecords
{
    public function __construct(private StudentGpaTally $tally) {}

    public function cgpaFor(int $studentId): ?float
    {
        return GpaCalculator::average(GpaCalculator::nonSuperseded($this->tally->recordFor($studentId)->attempts));
    }

    public function effectiveAttemptsForStudent(int $studentId): array
    {
        $record = $this->tally->recordFor($studentId);
        $enrollments = collect($record->enrollments)->keyBy(fn ($enrollment): int => $enrollment->id);

        return array_map(function (GpaAttempt $attempt) use ($record, $enrollments): EffectiveAttemptSummary {
            $enrollment = $enrollments->get($attempt->enrollmentId);
            $grade = $record->grades[$attempt->enrollmentId];

            return new EffectiveAttemptSummary(
                enrollmentId: $attempt->enrollmentId,
                courseId: $attempt->courseId,
                classification: $enrollment->classification,
                creditHours: $attempt->creditHours,
                earned: $attempt->earned === true,
                letterGrade: $grade->letter_grade->value,
            );
        }, GpaCalculator::nonSuperseded($record->attempts));
    }

    public function semesterGpaFor(int $studentId, int $semesterId): ?float
    {
        $eligible = GpaCalculator::nonSuperseded($this->tally->recordFor($studentId)->attempts);
        $inSemester = array_values(array_filter($eligible, fn (GpaAttempt $a): bool => $a->semesterId === $semesterId));

        return GpaCalculator::average($inSemester);
    }

    public function gradesFor(array $enrollmentIds): array
    {
        $grades = CurrentGradeLookup::forEnrollments($enrollmentIds);

        $summaries = [];
        foreach ($grades as $enrollmentId => $grade) {
            $summaries[$enrollmentId] = new GradeSummary(
                mark: (float) $grade->mark,
                letterGrade: $grade->letter_grade->value,
                gpaPoints: (float) $grade->gpa_points,
            );
        }

        return $summaries;
    }
}
