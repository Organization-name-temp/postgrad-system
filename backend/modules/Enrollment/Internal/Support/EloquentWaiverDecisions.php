<?php

namespace Modules\Enrollment\Internal\Support;

use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Public\WaiverDecisions;
use Modules\Enrollment\Public\WaiverDecisionSummary;

final class EloquentWaiverDecisions implements WaiverDecisions
{
    public function effectiveForStudent(int $studentId): array
    {
        return $this->effectiveForStudents([$studentId])[$studentId] ?? [];
    }

    public function effectiveForStudents(array $studentIds): array
    {
        $result = [];
        foreach (array_chunk(array_values(array_unique($studentIds)), 100) as $ids) {
            $grouped = WaiverDecision::query()->effective()->whereIn('student_id', $ids)->orderBy('id')->get()->groupBy('student_id');
            foreach ($grouped as $studentId => $decisions) {
                $result[(int) $studentId] = $decisions->map(
                    fn (WaiverDecision $decision): WaiverDecisionSummary => new WaiverDecisionSummary(
                        id: $decision->getKey(), requestId: $decision->waiver_request_id,
                        studentId: $decision->student_id, curriculumVersionId: $decision->curriculum_version_id,
                        targetCourseId: $decision->target_course_id, targetCourseCode: $decision->target_course_code,
                        creditHours: $decision->credit_hours, priorCourseId: $decision->prior_course_id,
                        priorCourseCode: $decision->prior_course_code, priorCourseLevel: $decision->prior_course_level,
                        priorResultSource: $decision->prior_result_source, priorLetterGrade: $decision->prior_letter_grade,
                        coveragePercentage: $decision->coverage_percentage, verifiedBy: $decision->verified_by,
                        verifiedAt: $decision->verified_at->toIso8601String(), approvedBy: $decision->approved_by,
                        approvedAt: $decision->approved_at->toIso8601String(),
                    ),
                )->all();
            }
        }

        return $result;
    }
}
