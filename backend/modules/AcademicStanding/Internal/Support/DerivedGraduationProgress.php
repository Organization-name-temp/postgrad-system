<?php

namespace Modules\AcademicStanding\Internal\Support;

use Modules\AcademicStanding\Public\AcademicStanding;
use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\AcademicStanding\Public\GraduationProgress;
use Modules\AcademicStanding\Public\GraduationReadiness;
use Modules\AcademicStanding\Public\ProposedCourse;
use Modules\AcademicStanding\Public\StandingRecords;
use Modules\AcademicStanding\Public\UnderloadEligibility;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Enrollment\Public\WaiverDecisions;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Public\Contracts\StudentDirectory;

final readonly class DerivedGraduationProgress implements GraduationProgress
{
    public function __construct(
        private StudentDirectory $students,
        private CurriculumCatalog $curricula,
        private CreditHourSummaries $creditHours,
        private GradingRecords $grading,
        private StandingRecords $standing,
        private ExemptionDecisions $exemptions,
        private TransferDecisions $transfers,
        private WaiverDecisions $waivers,
    ) {}

    public function readinessForStudent(int $studentId, array $proposedCourses = []): ?GraduationReadiness
    {
        $summary = $this->creditHours->forStudent($studentId);
        if ($summary === null) {
            return null;
        }

        $remaining = $summary->rows[CreditHourCategory::Total->value]->remainingCh;

        return GraduationReadinessCalculator::calculate(
            remainingCreditHours: $remaining,
            cgpa: $this->grading->cgpaFor($studentId),
            terminationPending: $this->standing->currentForStudent($studentId)->standing === AcademicStanding::TerminationPending,
            proposedScheduleCompletesRequirements: $this->scheduleCompletes($studentId, $proposedCourses),
            minimumCgpa: (float) config('academic_standing.minimum_cgpa', 3.0),
        );
    }

    public function underloadEligibility(int $studentId, bool $isEnrollmentSemester, array $proposedCourses): UnderloadEligibility
    {
        $summary = $this->creditHours->forStudent($studentId);
        if ($summary === null) {
            // Legacy development fixtures admitted before curriculum binding retain the
            // original first-term rule; every real accepted Student has a bound version.
            return new UnderloadEligibility($isEnrollmentSemester, false);
        }

        return UnderloadRuleCalculator::calculate(
            isEnrollmentSemester: $isEnrollmentSemester,
            remainingPreparatoryTargets: $this->remainingExactTargets($studentId, CourseCategory::Preparatory),
            remainingExactTargets: $this->remainingExactTargets($studentId),
            remainingElectiveCh: $summary->rows[CreditHourCategory::ResearchElective->value]->remainingCh,
            totalRemainingCh: $summary->rows[CreditHourCategory::Total->value]->remainingCh,
            proposedCourses: $proposedCourses,
        );
    }

    /** @param list<ProposedCourse> $proposedCourses */
    private function scheduleCompletes(int $studentId, array $proposedCourses): bool
    {
        $forCredit = array_values(array_filter($proposedCourses, fn (ProposedCourse $course): bool => $course->forCredit));
        if (! $this->containsEveryTarget($forCredit, $this->remainingExactTargets($studentId))) {
            return false;
        }

        $summary = $this->creditHours->forStudent($studentId);
        if ($summary === null) {
            return false;
        }
        $electiveRemaining = $summary->rows[CreditHourCategory::ResearchElective->value]->remainingCh;
        $proposedElectiveCh = array_sum(array_map(
            fn (ProposedCourse $course): int => $course->category === CreditHourCategory::ResearchElective ? $course->creditHours : 0,
            $forCredit,
        ));

        return $proposedElectiveCh >= $electiveRemaining;
    }

    /** @return list<int> */
    private function remainingExactTargets(int $studentId, ?CourseCategory $only = null): array
    {
        $student = $this->students->findByStudentId($studentId);
        if ($student === null) {
            return [];
        }

        $satisfied = [];
        foreach ($this->exemptions->effectiveForStudent($studentId) as $decision) {
            $satisfied[$decision->targetCourseId] = true;
        }
        foreach ($this->transfers->effectiveForStudent($studentId) as $decision) {
            $satisfied[$decision->targetCourseId] = true;
        }
        foreach ($this->waivers->effectiveForStudent($studentId) as $decision) {
            $satisfied[$decision->targetCourseId] = true;
        }
        foreach ($this->grading->effectiveAttemptsForStudent($studentId) as $attempt) {
            if ($attempt->earned && ! isset($satisfied[$attempt->courseId])) {
                $satisfied[$attempt->courseId] = true;
            }
        }

        $targets = [];
        foreach ($this->curricula->entriesForVersion($student->curriculumVersionId) as $entry) {
            if ($entry->category === CourseCategory::ResearchElective || ($only !== null && $entry->category !== $only)) {
                continue;
            }
            if (! isset($satisfied[$entry->courseId])) {
                $targets[] = $entry->courseId;
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param  list<ProposedCourse>  $courses
     * @param  list<int>  $targets
     */
    private function containsEveryTarget(array $courses, array $targets): bool
    {
        $proposed = array_fill_keys(array_map(fn (ProposedCourse $course): int => $course->courseId, $courses), true);

        foreach ($targets as $target) {
            if (! isset($proposed[$target])) {
                return false;
            }
        }

        return true;
    }
}
