<?php

namespace Modules\AcademicStanding\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\AcademicStanding\Public\TerminationCaseSummary;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\SemesterSummary;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Public\EnrollmentRecords;
use Modules\Enrollment\Public\EnrollmentSummary;
use Modules\Enrollment\Public\ExemptionDecisionEvidenceSummary;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\ExemptionDecisionSummary;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Enrollment\Public\RemediationObligationSummary;
use Modules\Grading\Public\EffectiveAttemptSummary;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
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

    public function latestForStudent(int $studentId): ?TerminationCaseSummary
    {
        $case = TerminationCase::query()->where('student_id', $studentId)->latest('id')->first();

        return $case === null ? null : $this->summary($case);
    }

    public function reconcile(int $studentId): ?TerminationCaseSummary
    {
        return DB::transaction(function () use ($studentId): ?TerminationCaseSummary {
            $student = $this->students->lock($studentId);
            if ($student === null || $student->lifecycleStatus !== StudentLifecycleStatus::Active) {
                return null;
            }
            $evidence = $this->evidence($studentId, $student->enrollmentTerm, $student->curriculumVersionId);
            $fingerprint = hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR));
            $open = TerminationCase::query()->where('student_id', $studentId)->whereIn('state', $this->openStates())->lockForUpdate()->first();
            if ($evidence === []) {
                if ($open !== null) {
                    $open->update(['state' => 'TriggerCleared', 'closed_reason' => 'TriggerCleared', 'closed_at' => now()]);
                    $payload = ['case_id' => $open->id, 'state' => 'TriggerCleared', 'change' => 'trigger_cleared'];
                    foreach (array_unique([$student->userId, $student->advisorId]) as $recipient) {
                        $this->notifier->notifyOnce($recipient, NotificationType::TerminationCaseChanged, "termination-case:{$open->id}:trigger-cleared:{$recipient}", $payload);
                    }
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
            fn ($attempt): array => ['course_id' => $attempt->courseId, 'grade_id' => $attempt->gradeId, 'enrollment_id' => $attempt->enrollmentId, 'letter_grade' => $attempt->letterGrade],
            array_filter($attempts, fn ($attempt): bool => in_array($attempt->letterGrade, ['D', 'F'], true)),
        ));
        usort($grades, fn (array $a, array $b): int => [$a['course_id'], $a['grade_id']] <=> [$b['course_id'], $b['grade_id']]);
        $components = [];
        if (count(array_unique(array_column($grades, 'course_id'))) >= 2) {
            $components['df_grades'] = $grades;
        }

        $enrollments = $this->enrollments->enrollmentsForStudent($studentId);
        $incompleteCourseIds = array_fill_keys(array_map(fn ($item): int => $item->courseId, array_filter($enrollments, fn ($item): bool => $item->courseStatus === 'Incomplete')), true);
        $earnedCourseIds = array_fill_keys(array_map(fn ($attempt): int => $attempt->courseId, array_filter($attempts, fn ($attempt): bool => $attempt->earned)), true);
        $effectiveExemptions = $this->exemptions->effectiveForStudent($studentId);
        $exemptionEvidence = $this->exemptions->evidenceForStudent($studentId);
        $exemptCourseIds = array_fill_keys(array_map(fn ($decision): int => $decision->targetCourseId, $effectiveExemptions), true);
        $remediation = $this->remediation->forStudent($studentId);
        $suppressedCourseIds = array_fill_keys(array_map(fn ($item): int => $item->courseId, array_filter($remediation, fn ($item): bool => in_array($item->state, ['AwaitingOffering', 'Active', 'Evaluated'], true))), true);
        $ordinaryCheckpoint = $this->ordinaryCheckpoint($enrollmentTerm);
        if ($ordinaryCheckpoint !== null && now()->greaterThanOrEqualTo($ordinaryCheckpoint->gradeSubmissionDeadlineAt)) {
            $requirements = array_values(array_filter($this->curricula->entriesForVersion($curriculumVersionId), fn ($entry): bool => $entry->category === CourseCategory::Preparatory && ! isset($exemptCourseIds[$entry->courseId])));
            usort($requirements, fn ($a, $b): int => [$a->courseId, $a->ordinal] <=> [$b->courseId, $b->ordinal]);
            $unsatisfied = array_values(array_filter($requirements, fn ($entry): bool => ! isset($earnedCourseIds[$entry->courseId]) && ! isset($suppressedCourseIds[$entry->courseId])));
            $deferred = array_filter($unsatisfied, fn ($entry): bool => isset($incompleteCourseIds[$entry->courseId]));
            if ($unsatisfied !== [] && $deferred === []) {
                $components['preparatory_noncompletion'] = array_map(fn ($entry): array => [
                    'requirement_id' => $curriculumVersionId.':'.$entry->courseId,
                    'curriculum_version_id' => $curriculumVersionId,
                    'course_id' => $entry->courseId,
                    'requirement_role' => $entry->preparatoryRequirementRole?->value,
                    'outcome' => 'unsatisfied',
                    'checkpoint' => ['source' => 'ordinary', 'semester_id' => $ordinaryCheckpoint->id, 'deadline' => $ordinaryCheckpoint->gradeSubmissionDeadlineAt->toIso8601String()],
                    ...$this->courseEvidence($entry->courseId, $attempts, $enrollments, $effectiveExemptions, $exemptionEvidence, $remediation),
                ], $unsatisfied);
            }
        }

        $expired = [];
        foreach ($remediation as $item) {
            if ($item->state === 'Evaluated' && ! isset($earnedCourseIds[$item->courseId]) && ! isset($exemptCourseIds[$item->courseId])) {
                $expired[] = ['obligation_id' => $item->id, 'course_id' => $item->courseId, 'checkpoint' => ['source' => 'remediation', 'obligation_id' => $item->id, 'semester_id' => $item->checkpointSemesterId, 'deadline' => $item->effectiveDeadline], ...$this->courseEvidence($item->courseId, $attempts, $enrollments, $effectiveExemptions, $exemptionEvidence, $remediation)];

                continue;
            }
            if ($item->state !== 'Active' || $item->effectiveDeadline === null || ! now()->greaterThanOrEqualTo($item->effectiveDeadline) || isset($incompleteCourseIds[$item->courseId])) {
                continue;
            }
            if (isset($earnedCourseIds[$item->courseId]) || isset($exemptCourseIds[$item->courseId])) {
                continue;
            }
            $this->remediation->markEvaluated($item->id, $item->effectiveDeadline);
            $expired[] = ['obligation_id' => $item->id, 'course_id' => $item->courseId, 'checkpoint' => ['source' => 'remediation', 'obligation_id' => $item->id, 'semester_id' => $item->checkpointSemesterId, 'deadline' => $item->effectiveDeadline], ...$this->courseEvidence($item->courseId, $attempts, $enrollments, $effectiveExemptions, $exemptionEvidence, $remediation)];
        }
        if ($expired !== []) {
            usort($expired, fn (array $a, array $b): int => [$a['course_id'], $a['obligation_id']] <=> [$b['course_id'], $b['obligation_id']]);
            $components['remediation'] = $expired;
        }

        return $components;
    }

    private function ordinaryCheckpoint(string $enrollmentTerm): ?SemesterSummary
    {
        $first = $this->calendar->findByNameEn($enrollmentTerm);

        return $first === null ? null : $this->calendar->nextAfter($first->id);
    }

    /**
     * @param  list<EffectiveAttemptSummary>  $attempts
     * @param  list<EnrollmentSummary>  $enrollments
     * @param  list<ExemptionDecisionSummary>  $effectiveExemptions
     * @param  list<ExemptionDecisionEvidenceSummary>  $exemptionEvidence
     * @param  list<RemediationObligationSummary>  $remediation
     * @return array<string, mixed>
     */
    private function courseEvidence(int $courseId, array $attempts, array $enrollments, array $effectiveExemptions, array $exemptionEvidence, array $remediation): array
    {
        $attempt = collect($attempts)->first(fn ($item): bool => $item->courseId === $courseId);
        $withdrawalIds = array_values(array_map(fn ($item): int => $item->id, array_filter($enrollments, fn ($item): bool => $item->courseId === $courseId && $item->courseStatus === 'Withdrawn')));
        $exemptionIds = array_values(array_map(fn ($item): int => $item->id, array_filter($effectiveExemptions, fn ($item): bool => $item->targetCourseId === $courseId)));
        $revocationIds = array_values(array_map(fn ($item): int => $item->revocationId, array_filter($exemptionEvidence, fn ($item): bool => $item->targetCourseId === $courseId && $item->revocationId !== null)));
        $obligationRevocations = array_values(array_map(fn ($item): int => $item->revocationId, array_filter($remediation, fn ($item): bool => $item->courseId === $courseId)));
        sort($withdrawalIds);
        sort($exemptionIds);
        $revocationIds = array_values(array_unique([...$revocationIds, ...$obligationRevocations]));
        sort($revocationIds);

        return [
            'effective_grade' => $attempt === null
                ? ['kind' => 'no_attempt']
                : ['kind' => 'grade', 'grade_id' => $attempt->gradeId, 'enrollment_id' => $attempt->enrollmentId, 'letter_grade' => $attempt->letterGrade],
            'withdrawal_enrollment_ids' => $withdrawalIds,
            'effective_exemption_ids' => $exemptionIds,
            'revocation_ids' => $revocationIds,
        ];
    }

    /** @return list<string> */
    private function openStates(): array
    {
        return ['AwaitingAdvisor', 'AwaitingDepartmentHead', 'AwaitingCouncil'];
    }

    private function summary(TerminationCase $case): TerminationCaseSummary
    {
        return new TerminationCaseSummary(
            $case->id,
            $case->student_id,
            $case->fingerprint,
            $case->state,
            $case->evidence,
            $case->getAttribute('advisor_user_id'),
            $case->getAttribute('advisor_assessment'),
            $case->getAttribute('advisor_recommendation'),
            $case->getAttribute('department_head_user_id'),
            $case->getAttribute('department_head_reason'),
            $case->getAttribute('council_user_id'),
            $case->getAttribute('council_reason'),
            $case->getAttribute('closed_reason'),
            $case->closed_at?->toIso8601String(),
        );
    }
}
