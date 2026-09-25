<?php

namespace Modules\AcademicStanding\Internal\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\AcademicStanding\Public\TerminationCaseSummary;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

final readonly class TerminationCaseReconciler implements TerminationCases
{
    public function __construct(
        private StudentGuard $students,
        private GradingRecords $grading,
        private RemediationObligations $remediation,
        private Notifier $notifier,
        private AcademicCalendar $calendar,
        private CurriculumCatalog $curricula,
        private EnrollmentRecords $enrollments,
        private ExemptionDecisions $exemptions,
    ) {}

    public function currentForStudent(int $studentId): ?TerminationCaseSummary
    {
        $case = TerminationCase::query()->where('student_id', $studentId)->whereIn('state', $this->openStates())->latest('id')->first();

        return $case === null ? null : $this->summary($case);
    }

    public function reconcile(int $studentId): ?TerminationCaseSummary
    {
        return DB::transaction(function () use ($studentId): ?TerminationCaseSummary {
            $student = $this->students->lock($studentId);
            if ($student === null) {
                return null;
            }
            $evidence = $this->evidence($studentId, $student->enrollmentTerm, $student->curriculumVersionId);
            $fingerprint = hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR));
            $open = TerminationCase::query()->where('student_id', $studentId)->whereIn('state', $this->openStates())->lockForUpdate()->first();
            if ($evidence === []) {
                if ($open !== null) {
                    $open->update(['state' => 'TriggerCleared', 'closed_reason' => 'TriggerCleared', 'closed_at' => now()]);
                }

                return null;
            }
            if ($open !== null && $open->fingerprint === $fingerprint) {
                return $this->summary($open);
            }
            if ($open !== null) {
                $open->update(['state' => 'EvidenceChanged', 'closed_reason' => 'EvidenceChanged', 'closed_at' => now()]);
            }
            if (TerminationCase::query()->where('student_id', $studentId)->where('fingerprint', $fingerprint)->where('state', 'Dismissed')->exists()) {
                return null;
            }
            $case = TerminationCase::query()->create(['student_id' => $studentId, 'fingerprint' => $fingerprint, 'state' => 'AwaitingAdvisor', 'evidence' => $evidence]);
            $payload = ['case_id' => $case->id, 'state' => $case->state, 'evidence' => $evidence];
            $this->notifier->notifyOnce($student->userId, NotificationType::TerminationCaseChanged, 'termination-case:'.$case->id.':student', $payload);
            $this->notifier->notifyOnce($student->advisorId, NotificationType::TerminationCaseChanged, 'termination-case:'.$case->id.':advisor', $payload);

            return $this->summary($case);
        });
    }

    /** @return array<string, mixed> */
    private function evidence(int $studentId, string $enrollmentTerm, int $curriculumVersionId): array
    {
        $attempts = $this->grading->effectiveAttemptsForStudent($studentId);
        $grades = array_values(array_map(
            fn ($attempt): array => ['course_id' => $attempt->courseId, 'enrollment_id' => $attempt->enrollmentId, 'letter_grade' => $attempt->letterGrade],
            array_filter($attempts, fn ($attempt): bool => in_array($attempt->letterGrade, ['D', 'F'], true)),
        ));
        usort($grades, fn (array $a, array $b): int => $a['course_id'] <=> $b['course_id']);
        $components = [];
        if (count(array_unique(array_column($grades, 'course_id'))) >= 2) {
            $components['df_grades'] = $grades;
        }

        $enrollments = $this->enrollments->enrollmentsForStudent($studentId);
        $incompleteCourseIds = array_fill_keys(array_map(fn ($item): int => $item->courseId, array_filter($enrollments, fn ($item): bool => $item->courseStatus === 'Incomplete')), true);
        $earnedCourseIds = array_fill_keys(array_map(fn ($attempt): int => $attempt->courseId, array_filter($attempts, fn ($attempt): bool => $attempt->earned)), true);
        $exemptCourseIds = array_fill_keys(array_map(fn ($decision): int => $decision->targetCourseId, $this->exemptions->effectiveForStudent($studentId)), true);
        $remediation = $this->remediation->forStudent($studentId);
        $suppressedCourseIds = array_fill_keys(array_map(fn ($item): int => $item->courseId, array_filter($remediation, fn ($item): bool => in_array($item->state, ['AwaitingOffering', 'Active', 'Evaluated'], true))), true);
        $ordinaryDeadline = $this->ordinaryDeadline($enrollmentTerm);
        if ($ordinaryDeadline !== null && now()->greaterThanOrEqualTo($ordinaryDeadline)) {
            $requirements = array_values(array_filter($this->curricula->entriesForVersion($curriculumVersionId), fn ($entry): bool => $entry->category === CourseCategory::Preparatory && ! isset($exemptCourseIds[$entry->courseId])));
            $unsatisfied = array_values(array_filter($requirements, fn ($entry): bool => ! isset($earnedCourseIds[$entry->courseId]) && ! isset($suppressedCourseIds[$entry->courseId])));
            $deferred = array_filter($unsatisfied, fn ($entry): bool => isset($incompleteCourseIds[$entry->courseId]));
            if ($unsatisfied !== [] && $deferred === []) {
                $components['preparatory_noncompletion'] = array_map(fn ($entry): array => ['course_id' => $entry->courseId, 'requirement_role' => $entry->preparatoryRequirementRole?->value, 'outcome' => 'unsatisfied', 'checkpoint' => $ordinaryDeadline->toIso8601String()], $unsatisfied);
            }
        }

        $expired = [];
        foreach ($remediation as $item) {
            if ($item->state === 'Evaluated' && ! isset($earnedCourseIds[$item->courseId]) && ! isset($exemptCourseIds[$item->courseId])) {
                $expired[] = ['obligation_id' => $item->id, 'course_id' => $item->courseId, 'deadline' => $item->effectiveDeadline];

                continue;
            }
            if ($item->state !== 'Active' || $item->effectiveDeadline === null || ! now()->greaterThanOrEqualTo($item->effectiveDeadline) || isset($incompleteCourseIds[$item->courseId])) {
                continue;
            }
            if (isset($earnedCourseIds[$item->courseId]) || isset($exemptCourseIds[$item->courseId])) {
                continue;
            }
            $this->remediation->markEvaluated($item->id, $item->effectiveDeadline);
            $expired[] = ['obligation_id' => $item->id, 'course_id' => $item->courseId, 'deadline' => $item->effectiveDeadline];
        }
        if ($expired !== []) {
            $components['remediation'] = $expired;
        }

        return $components;
    }

    private function ordinaryDeadline(string $enrollmentTerm): ?CarbonInterface
    {
        $first = $this->calendar->findByNameEn($enrollmentTerm);

        return $first === null ? null : $this->calendar->nextAfter($first->id)?->gradeSubmissionDeadlineAt;
    }

    /** @return list<string> */
    private function openStates(): array
    {
        return ['AwaitingAdvisor', 'AwaitingDepartmentHead', 'AwaitingCouncil'];
    }

    private function summary(TerminationCase $case): TerminationCaseSummary
    {
        return new TerminationCaseSummary($case->id, $case->student_id, $case->fingerprint, $case->state, $case->evidence);
    }
}
