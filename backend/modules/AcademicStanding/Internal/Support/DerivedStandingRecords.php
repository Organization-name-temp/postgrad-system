<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Internal\Models\StandingObservation;
use Modules\AcademicStanding\Public\AcademicStanding;
use Modules\AcademicStanding\Public\StandingRecords;
use Modules\AcademicStanding\Public\StandingSummary;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradingRecords;

final readonly class DerivedStandingRecords implements StandingRecords
{
    public function __construct(private GradingRecords $grading, private TerminationCases $terminationCases) {}

    public function currentForStudent(int $studentId): StandingSummary
    {
        return $this->summary(
            $studentId,
            $this->grading->cgpaFor($studentId),
            $this->grading->effectiveAttemptsForStudent($studentId),
            $this->terminationCases->currentForStudent($studentId) !== null,
        );
    }

    public function currentForStudents(array $studentIds): array
    {
        $values = [];
        foreach (array_chunk(array_values(array_unique($studentIds)), 100) as $batch) {
            $cgpas = $this->grading->cgpaForStudents($batch);
            $attempts = $this->grading->effectiveAttemptsForStudents($batch);
            $cases = $this->terminationCases->currentForStudents($batch);
            foreach ($batch as $studentId) {
                $values[$studentId] = $this->summary($studentId, $cgpas[$studentId], $attempts[$studentId], isset($cases[$studentId]));
            }
        }

        return $values;
    }

    /** @param list<EffectiveAttemptSummary> $attempts */
    private function summary(int $studentId, ?float $cgpa, array $attempts, bool $terminationPending): StandingSummary
    {
        $standing = StandingCalculator::calculate(
            $cgpa,
            array_map(fn ($attempt): bool => $attempt->earned, $attempts),
            $terminationPending,
        );

        return new StandingSummary(
            studentId: $studentId,
            standing: $standing,
            cgpa: $cgpa,
            explanation: match ($standing) {
                AcademicStanding::TerminationPending => 'termination_pending',
                AcademicStanding::NotYetAssessed => 'no_finalized_cgpa_grade',
                AcademicStanding::AcademicProbation => 'cgpa_below_minimum',
                AcademicStanding::AcademicWarning => 'effective_grade_below_c_plus',
                AcademicStanding::GoodStanding => 'requirements_met',
            },
            registrationCeilingCh: $standing === AcademicStanding::AcademicProbation
                || $standing === AcademicStanding::TerminationPending
                ? (int) config('academic_standing.probation_credit_hour_ceiling', 6)
                : null,
        );
    }

    public function observationsForStudent(int $studentId): array
    {
        return StandingObservation::query()->where('student_id', $studentId)->orderByDesc('id')->get()
            ->map(fn (StandingObservation $observation): array => [
                'id' => $observation->id,
                'standing' => $observation->standing->value,
                'cause_type' => $observation->cause_type,
                'cause_id' => $observation->cause_id,
                'observed_at' => $observation->observed_at->toIso8601String(),
            ])->all();
    }
}
