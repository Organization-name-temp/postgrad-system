<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Public\TransferDecisions;
use Modules\Enrollment\Public\TransferDecisionSummary;

final class EloquentTransferDecisions implements TransferDecisions
{
    public function effectiveForStudent(int $studentId): array
    {
        return $this->effectiveForStudents([$studentId])[$studentId] ?? [];
    }

    public function effectiveForStudents(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return TransferDecision::query()
            ->effective()
            ->whereIn('student_id', array_values(array_unique($studentIds)))
            ->orderBy('id')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($items): array => $items->map(fn (TransferDecision $decision): TransferDecisionSummary => new TransferDecisionSummary(
                id: (int) $decision->getKey(),
                studentId: $decision->student_id,
                curriculumVersionId: $decision->curriculum_version_id,
                targetCourseId: $decision->target_course_id,
                targetCourseCode: $decision->target_course_code,
                targetCategory: $decision->target_category->value,
                targetCreditHours: $decision->target_credit_hours,
                externalInstitution: $decision->external_institution,
                externalCourseCode: $decision->external_course_code,
                externalCourseName: $decision->external_course_name,
                sourceCreditHours: $decision->source_credit_hours,
                externalGrade: $decision->external_grade,
                documentIds: $decision->document_ids,
                normalizedGrade: $decision->normalized_grade,
                verifiedByUserId: $decision->verified_by_user_id,
                verifiedAt: $decision->verified_at,
                approvedByUserId: $decision->approved_by_user_id,
                approvedAt: $decision->approved_at,
            ))->all())
            ->all();
    }
}
