<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\AcademicStanding\Public\CreditHourSummary;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Enrollment\Public\WaiverDecisions;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Public\Contracts\StudentDirectory;

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
        $targets = [];
        foreach ($entries as $entry) {
            $targets[$entry->courseId] = CreditHourCategory::from($entry->category->value);
        }

        $attended = array_values(array_filter(array_map(
            fn (EnrollmentSummary $enrollment): ?CreditFact => $enrollment->countsTowardAttendance
                ? new CreditFact(self::categoryForClassification($enrollment->classification), $enrollment->courseId, $enrollment->creditHours)
                : null,
            $this->enrollments->enrollmentsForStudent($studentId),
        )));
        $earned = array_values(array_filter(array_map(
            fn (EffectiveAttemptSummary $attempt): ?CreditFact => $attempt->earned
                ? new CreditFact(self::categoryForClassification($attempt->classification), $attempt->courseId, $attempt->creditHours)
                : null,
            $this->grading->effectiveAttemptsForStudent($studentId),
        )));
        $exempted = array_map(
            fn ($decision): CreditFact => new CreditFact(CreditHourCategory::Preparatory, $decision->targetCourseId, $decision->creditHours),
            $this->exemptions->effectiveForStudent($studentId),
        );
        $transferred = array_map(
            fn ($decision): CreditFact => new CreditFact(CreditHourCategory::from($decision->targetCategory), $decision->targetCourseId, $decision->targetCreditHours),
            $this->transfers->effectiveForStudent($studentId),
        );
        $waived = array_values(array_filter(array_map(
            fn ($decision): ?CreditFact => isset($targets[$decision->targetCourseId])
                ? new CreditFact($targets[$decision->targetCourseId], $decision->targetCourseId, $decision->creditHours)
                : null,
            $this->waivers->effectiveForStudent($studentId),
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
        foreach (array_values(array_unique($studentIds)) as $studentId) {
            $summary = $this->forStudent($studentId);
            if ($summary !== null) {
                $summaries[$studentId] = $summary;
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
