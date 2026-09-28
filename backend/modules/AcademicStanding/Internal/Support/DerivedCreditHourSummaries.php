<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\AcademicStanding\Public\CreditHourSummary;
use Modules\Catalog\Public\BoundCurriculumEntrySummary;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\CurriculumRequirementsSummary;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\ExemptionDecisionSummary;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Enrollment\Public\TransferDecisionSummary;
use Modules\Enrollment\Public\WaiverDecisions;
use Modules\Enrollment\Public\WaiverDecisionSummary;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\StudentSummary;

final readonly class DerivedCreditHourSummaries implements CreditHourSummaries
{
    public function __construct(
        private StudentDirectory $students,
        private CurriculumCatalog $curricula,
        private EnrollmentRecords $enrollments,
        private GradingRecords $grading,
        private ExemptionDecisions $exemptions,
        private TransferDecisions $transfers,
        private WaiverDecisions $waivers,
    ) {}

    public function forStudent(int $studentId): ?CreditHourSummary
    {
        $student = $this->students->findByStudentId($studentId);
        if ($student === null) {
            return null;
        }
        $requirements = $this->curricula->requirementsForVersion($student->curriculumVersionId);
        if ($requirements === null) {
            return null;
        }
        $entries = $this->curricula->entriesForVersion($student->curriculumVersionId);

        return $this->summarize($student, $requirements, $entries,
            $this->enrollments->enrollmentsForStudent($studentId),
            $this->grading->effectiveAttemptsForStudent($studentId),
            $this->exemptions->effectiveForStudent($studentId),
            $this->transfers->effectiveForStudent($studentId),
            $this->waivers->effectiveForStudent($studentId));
    }

    /**
     * @param  list<BoundCurriculumEntrySummary>  $entries
     * @param  list<EnrollmentSummary>  $enrollments
     * @param  list<EffectiveAttemptSummary>  $attempts
     * @param  list<ExemptionDecisionSummary>  $exemptions
     * @param  list<TransferDecisionSummary>  $transfers
     * @param  list<WaiverDecisionSummary>  $waivers
     */
    private function summarize(
        StudentSummary $student,
        CurriculumRequirementsSummary $requirements,
        array $entries, array $enrollments, array $attempts,
        array $exemptions, array $transfers, array $waivers,
    ): CreditHourSummary {
        $studentId = $student->studentId;
        $targets = [];
        foreach ($entries as $entry) {
            $targets[$entry->courseId] = CreditHourCategory::from($entry->category->value);
        }

        $attended = array_values(array_filter(array_map(
            fn (EnrollmentSummary $enrollment): ?CreditFact => $enrollment->countsTowardAttendance
                ? new CreditFact(self::categoryForClassification($enrollment->classification), $enrollment->courseId, $enrollment->creditHours)
                : null,
            $enrollments,
        )));
        $earned = array_values(array_filter(array_map(
            fn (EffectiveAttemptSummary $attempt): ?CreditFact => $attempt->earned
                ? new CreditFact(self::categoryForClassification($attempt->classification), $attempt->courseId, $attempt->creditHours)
                : null,
            $attempts,
        )));
        $exempted = array_map(
            fn ($decision): CreditFact => new CreditFact(CreditHourCategory::Preparatory, $decision->targetCourseId, $decision->creditHours),
            $exemptions,
        );
        $transferred = array_map(
            fn ($decision): CreditFact => new CreditFact(CreditHourCategory::from($decision->targetCategory), $decision->targetCourseId, $decision->targetCreditHours),
            $transfers,
        );
        $waived = array_values(array_filter(array_map(
            fn ($decision): ?CreditFact => isset($targets[$decision->targetCourseId])
                ? new CreditFact($targets[$decision->targetCourseId], $decision->targetCourseId, $decision->creditHours)
                : null,
            $waivers,
        )));

        $rows = CreditHourSummaryCalculator::calculate(
            baseline: [
                CourseCategory::Preparatory->value => $requirements->preparatoryCh,
                CourseCategory::ProgramCore->value => $requirements->programCoreCh,
                CourseCategory::ResearchElective->value => $requirements->researchElectiveCh,
            ],
            attended: $attended,
            earned: $earned,
            transferred: $transferred,
            waived: $waived,
            exempted: $exempted,
        );

        return new CreditHourSummary($studentId, $student->curriculumVersionId, $rows);
    }

    public function forStudents(array $studentIds): array
    {
        $summaries = [];
        foreach (array_chunk(array_values(array_unique($studentIds)), 100) as $ids) {
            $students = $this->students->findManyByStudentId($ids);
            $versions = array_values(array_unique(array_map(fn ($student): int => $student->curriculumVersionId, $students)));
            $requirements = $this->curricula->requirementsForVersions($versions);
            $entries = $this->curricula->entriesForVersions($versions);
            $enrollments = $this->enrollments->enrollmentsForStudents($ids);
            $attempts = $this->grading->effectiveAttemptsForStudents($ids);
            $exemptions = $this->exemptions->effectiveForStudents($ids);
            $transfers = $this->transfers->effectiveForStudents($ids);
            $waivers = $this->waivers->effectiveForStudents($ids);
            foreach ($students as $studentId => $student) {
                $baseline = $requirements[$student->curriculumVersionId] ?? null;
                if ($baseline !== null) {
                    $summaries[$studentId] = $this->summarize($student, $baseline,
                        $entries[$student->curriculumVersionId] ?? [], $enrollments[$studentId] ?? [],
                        $attempts[$studentId] ?? [], $exemptions[$studentId] ?? [],
                        $transfers[$studentId] ?? [], $waivers[$studentId] ?? []);
                }
            }
        }

        return $summaries;
    }

    private static function categoryForClassification(string $classification): CreditHourCategory
    {
        return match ($classification) {
            'Preparatory' => CreditHourCategory::Preparatory,
            'Core' => CreditHourCategory::ProgramCore,
            default => CreditHourCategory::ResearchElective,
        };
    }
}
