<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AcademicStanding\Internal\Support\StudentStandingScope;
use Modules\AcademicStanding\Public\GraduationProgress;
use Modules\Identity\Public\Contracts\StudentDirectory;

/** @group Academic Standing */
final class GraduationReadinessController
{
    /**
     * My derived Graduation Readiness.
     *
     * @response 200 {"data":{"ready":false,"remaining_credit_hours":3,"cgpa":3.25,"approaching_graduation":false,"blockers":["RemainingRequirements"]}}
     */
    public function mine(Request $request, StudentDirectory $students, GraduationProgress $progress): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403, __('academic_standing.student_required'));

        return $this->response($student->studentId, $progress);
    }

    /**
     * A scoped Student's derived Graduation Readiness.
     *
     * @urlParam student_id integer required The Student anchor id. Example: 1
     *
     * @response 200 {"data":{"ready":true,"remaining_credit_hours":0,"cgpa":3.4,"approaching_graduation":false,"blockers":[]}}
     */
    public function show(
        Request $request,
        int $student_id,
        StudentDirectory $students,
        StudentStandingScope $scope,
        GraduationProgress $progress,
    ): JsonResponse {
        $student = $students->findByStudentId($student_id);
        abort_if($student === null, 404, __('academic_standing.student_missing'));
        abort_unless($scope->permits((int) $request->user()?->getAuthIdentifier(), $student), 403);

        return $this->response($student_id, $progress);
    }

    private function response(int $studentId, GraduationProgress $progress): JsonResponse
    {
        $readiness = $progress->readinessForStudent($studentId);
        abort_if($readiness === null, 404, __('academic_standing.curriculum_missing'));

        return response()->json(['data' => $readiness->toArray()]);
    }
}
