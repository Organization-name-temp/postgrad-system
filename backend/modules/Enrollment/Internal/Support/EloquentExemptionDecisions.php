<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Public\ExemptionDecisionEvidenceSummary;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Enrollment\Public\ExemptionDecisionSummary;

final class EloquentExemptionDecisions implements ExemptionDecisions
{
    public function effectiveForStudent(int $studentId): array
    {
        return $this->effectiveForStudents([$studentId])[$studentId] ?? [];
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, list<ExemptionDecisionSummary>>
     */
    public function effectiveForStudents(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }
        $grouped = ExemptionDecision::query()->effective()->whereIn('student_id', array_values(array_unique($studentIds)))->orderBy('id')->get()->groupBy('student_id');

        return $grouped->map(fn ($items): array => $items->map(fn (ExemptionDecision $decision): ExemptionDecisionSummary => new ExemptionDecisionSummary(
            id: (int) $decision->getKey(),
            studentId: $decision->student_id,
            curriculumVersionId: $decision->curriculum_version_id,
            targetCourseId: $decision->target_course_id,
            targetCourseCode: $decision->target_course_code,
            preparatoryRequirementRole: $decision->preparatory_requirement_role->value,
            creditHours: $decision->credit_hours,
            source: $decision->source->value,
            reasonCodes: $decision->reason_codes,
            approvedAt: $decision->approved_at,
        ))->all())->all();
    }

    public function evidenceForStudent(int $studentId): array
    {
        return ExemptionDecision::query()->with('revocation')->where('student_id', $studentId)->orderBy('id')->get()
            ->map(fn (ExemptionDecision $decision): ExemptionDecisionEvidenceSummary => new ExemptionDecisionEvidenceSummary(
                decisionId: (int) $decision->getKey(),
                targetCourseId: $decision->target_course_id,
                revocationId: $decision->revocation === null ? null : (int) $decision->revocation->getKey(),
            ))->all();
    }
}
