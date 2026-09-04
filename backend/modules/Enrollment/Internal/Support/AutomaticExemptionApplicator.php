<?php

namespace Modules\Enrollment\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Admissions\Public\AdmissionAcceptanceEvidence;
use Modules\Catalog\Public\BoundCurriculumEntrySummary;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\AutomaticExemptionReason;
use Modules\Enrollment\Internal\Enums\ExemptionSource;
use Modules\Enrollment\Internal\Models\AutomaticExemptionEvidence;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use UnexpectedValueException;

class AutomaticExemptionApplicator
{
    public function __construct(
        private readonly CurriculumCatalog $curricula,
        private readonly StudentDirectory $students,
        private readonly AuditLogger $audit,
        private readonly Notifier $notifier,
    ) {}

    /** Persist the event snapshot independently of evaluation for recovery. */
    public function capture(AdmissionAcceptanceEvidence $evidence): void
    {
        AutomaticExemptionEvidence::query()->firstOrCreate(
            ['student_id' => $evidence->studentId],
            [
                'admission_application_id' => $evidence->applicationId,
                'evidence' => $this->evidenceArray($evidence),
                'captured_at' => now(),
            ],
        );
    }

    /** Apply every missing qualifying decision from the captured immutable snapshot. */
    public function applyForStudent(int $studentId): int
    {
        $student = $this->students->findByStudentId($studentId);
        if ($student === null) {
            throw new UnexpectedValueException("Student {$studentId} no longer resolves through Identity.");
        }

        $receipt = AutomaticExemptionEvidence::query()->where('student_id', $studentId)->first();
        if ($receipt === null && $student->admissionEvidence !== null) {
            $anchoredEvidence = ['student_id' => $studentId, ...$student->admissionEvidence->toArray()];
            $receipt = AutomaticExemptionEvidence::query()->firstOrCreate(
                ['student_id' => $studentId],
                [
                    'admission_application_id' => (int) ($anchoredEvidence['application_id'] ?? 0),
                    'evidence' => $anchoredEvidence,
                    'captured_at' => now(),
                ],
            );
        }
        if ($receipt === null) {
            throw new UnexpectedValueException("Student {$studentId} has no immutable admission evidence to reconcile.");
        }

        $evidence = $receipt->evidence;
        if ((int) ($evidence['user_id'] ?? 0) !== $student->userId
            || (int) ($evidence['admitted_program_id'] ?? 0) !== $student->programId) {
            throw new UnexpectedValueException("Admission evidence does not match Student {$studentId}.");
        }

        $entries = array_values(array_filter(
            $this->curricula->entriesForVersion($student->curriculumVersionId),
            static fn (BoundCurriculumEntrySummary $entry): bool => $entry->category === CourseCategory::Preparatory
                && $entry->preparatoryRequirementRole !== null
                && $entry->courseId > 0,
        ));
        $reasonsByRole = $this->qualifyingReasonsByRole($evidence);

        return DB::transaction(function () use ($entries, $evidence, $receipt, $student, $reasonsByRole): int {
            $created = 0;
            foreach ($entries as $entry) {
                $role = $entry->preparatoryRequirementRole;
                if ($role === null || ! isset($reasonsByRole[$role->value])) {
                    continue;
                }

                $reasons = $reasonsByRole[$role->value];
                $decision = ExemptionDecision::query()->firstOrCreate(
                    ['student_id' => $student->studentId, 'target_course_id' => $entry->courseId],
                    [
                        'curriculum_version_id' => $student->curriculumVersionId,
                        'target_course_code' => $entry->courseCode,
                        'preparatory_requirement_role' => $role,
                        'credit_hours' => $entry->totalCh,
                        'source' => ExemptionSource::Automatic,
                        'reason_codes' => $reasons,
                        'source_evidence' => $evidence,
                        'admission_application_id' => (int) $evidence['application_id'],
                        'approved_at' => now(),
                    ],
                );

                if (! $decision->wasRecentlyCreated) {
                    continue;
                }

                $created++;
                $this->audit->record(
                    action: 'exemption.automatically_applied',
                    metadata: [
                        'student_id' => $student->studentId,
                        'target_course_id' => $entry->courseId,
                        'curriculum_version_id' => $student->curriculumVersionId,
                        'admission_application_id' => (int) $evidence['application_id'],
                        'reason_codes' => $reasons,
                    ],
                    subjectType: 'exemption_decision',
                    subjectId: (string) $decision->getKey(),
                );
                $this->notifier->notifyOnce($student->userId, NotificationType::AutomaticExemptionApplied, 'exemption-decision:'.$decision->getKey(), [
                    'exemption_decision_id' => $decision->getKey(),
                    'course_code' => $entry->courseCode,
                    'preparatory_requirement_role' => $role->value,
                    'reason_codes' => $reasons,
                ]);
            }

            $receipt->forceFill(['reconciled_at' => now()])->save();

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $evidence
     * @return array<string, list<string>> keyed by Preparatory Requirement Role value
     */
    private function qualifyingReasonsByRole(array $evidence): array
    {
        /** @var array<string, list<string>> $reasons */
        $reasons = [];
        $add = static function (array &$target, PreparatoryRequirementRole $role, AutomaticExemptionReason $reason): void {
            $target[$role->value] ??= [];
            if (! in_array($reason->value, $target[$role->value], true)) {
                $target[$role->value][] = $reason->value;
            }
        };

        $test = (string) ($evidence['english_test'] ?? '');
        $score = $evidence['english_score'] ?? null;
        $englishQualified = is_numeric($score) && (($test === 'IELTS' && (float) $score >= 6.5)
            || ($test === 'TOEFL iBT' && (float) $score >= 79));
        if ($englishQualified) {
            $add($reasons, PreparatoryRequirementRole::EnglishLanguage, AutomaticExemptionReason::EnglishProficiency);
        }

        $isEjust = (bool) ($evidence['is_ejust_graduate'] ?? false);
        $previousDegree = (string) ($evidence['previous_degree'] ?? '');
        if ($isEjust && $previousDegree === 'BSc') {
            foreach ([
                PreparatoryRequirementRole::JapaneseLanguage,
                PreparatoryRequirementRole::JapaneseCulture,
                PreparatoryRequirementRole::KeySkills,
            ] as $role) {
                $add($reasons, $role, AutomaticExemptionReason::EjustBsc);
            }
        }

        if ($isEjust && $previousDegree === 'MSc' && ($evidence['previous_ejust_program_id'] ?? null) !== null) {
            if (($evidence['admitted_program_degree_level'] ?? null) === 'PhD'
                && ($evidence['previous_ejust_program_degree_level'] ?? null) === 'MSc'
                && (int) ($evidence['admitted_department_id'] ?? 0) > 0
                && (int) ($evidence['admitted_department_id'] ?? 0) === (int) ($evidence['previous_ejust_department_id'] ?? -1)) {
                foreach (PreparatoryRequirementRole::cases() as $role) {
                    $add($reasons, $role, AutomaticExemptionReason::SameDepartmentEjustMsc);
                }
            }
        }

        return $reasons;
    }

    /** @return array<string, mixed> */
    private function evidenceArray(AdmissionAcceptanceEvidence $evidence): array
    {
        return [
            'student_id' => $evidence->studentId,
            'user_id' => $evidence->userId,
            'application_id' => $evidence->applicationId,
            'admitted_program_id' => $evidence->admittedProgramId,
            'admitted_program_degree_level' => $evidence->admittedProgramDegreeLevel,
            'admitted_department_id' => $evidence->admittedDepartmentId,
            'previous_degree' => $evidence->previousDegree,
            'is_ejust_graduate' => $evidence->isEjustGraduate,
            'previous_ejust_program_id' => $evidence->previousEjustProgramId,
            'previous_ejust_program_degree_level' => $evidence->previousEjustProgramDegreeLevel,
            'previous_ejust_department_id' => $evidence->previousEjustDepartmentId,
            'english_test' => $evidence->englishTest,
            'english_score' => $evidence->englishScore,
        ];
    }
}
