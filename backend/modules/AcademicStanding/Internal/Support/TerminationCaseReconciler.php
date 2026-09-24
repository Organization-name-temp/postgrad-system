<?php

namespace Modules\AcademicStanding\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\AcademicStanding\Public\TerminationCaseSummary;
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
            $evidence = $this->evidence($studentId);
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
    private function evidence(int $studentId): array
    {
        $grades = array_values(array_map(
            fn ($attempt): array => ['course_id' => $attempt->courseId, 'enrollment_id' => $attempt->enrollmentId, 'letter_grade' => $attempt->letterGrade],
            array_filter($this->grading->effectiveAttemptsForStudent($studentId), fn ($attempt): bool => in_array($attempt->letterGrade, ['D', 'F'], true)),
        ));
        usort($grades, fn (array $a, array $b): int => $a['course_id'] <=> $b['course_id']);
        $components = [];
        if (count(array_unique(array_column($grades, 'course_id'))) >= 2) {
            $components['df_grades'] = $grades;
        }
        $expired = array_values(array_filter($this->remediation->forStudent($studentId), fn ($item): bool => $item->state === 'Active' && $item->effectiveDeadline !== null && now()->greaterThan($item->effectiveDeadline)));
        if ($expired !== []) {
            $components['remediation'] = array_map(fn ($item): array => ['obligation_id' => $item->id, 'course_id' => $item->courseId, 'deadline' => $item->effectiveDeadline], $expired);
        }

        return $components;
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
