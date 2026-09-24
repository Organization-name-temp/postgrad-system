<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AcademicStanding\Internal\Support\StudentStandingScope;
use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\Identity\Public\Contracts\StudentDirectory;

/** @group Academic Standing */
final class CreditHourSummaryController
{
    /**
     * My target-aware Credit-Hour Summary.
     *
     * @response 200 {"data":{"student_id":1,"curriculum_version_id":1,"categories":[{"category":"Preparatory","baseline_required_ch":6,"adjusted_required_ch":5,"attended_ch":2,"earned_ch":1,"transferred_ch":0,"waived_ch":0,"exempted_ch":1,"satisfied_ch":0,"remaining_ch":5}],"total":{"category":"Total","baseline_required_ch":21,"adjusted_required_ch":20,"attended_ch":2,"earned_ch":1,"transferred_ch":0,"waived_ch":0,"exempted_ch":1,"satisfied_ch":0,"remaining_ch":20}}}
     */
    public function mine(Request $request, StudentDirectory $students, CreditHourSummaries $summaries): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403, __('academic_standing.student_required'));
        $summary = $summaries->forStudent($student->studentId);
        abort_if($summary === null, 409, __('academic_standing.curriculum_unavailable'));

        return response()->json(['data' => $summary->toArray()]);
    }

    /**
     * A scoped Student's target-aware Credit-Hour Summary.
     *
     * @urlParam student_id integer required The Student anchor id. Example: 1
     *
     * @response 200 {"data":{"student_id":1,"curriculum_version_id":1,"categories":[],"total":{"category":"Total","baseline_required_ch":21,"adjusted_required_ch":21,"attended_ch":0,"earned_ch":0,"transferred_ch":0,"waived_ch":0,"exempted_ch":0,"satisfied_ch":0,"remaining_ch":21}}}
     */
    public function show(
        Request $request,
        int $student_id,
        StudentDirectory $students,
        StudentStandingScope $scope,
        CreditHourSummaries $summaries,
    ): JsonResponse {
        $student = $students->findByStudentId($student_id);
        abort_if($student === null, 404, __('academic_standing.student_missing'));
        abort_unless($scope->permits((int) $request->user()?->getAuthIdentifier(), $student), 403);
        $summary = $summaries->forStudent($student_id);
        abort_if($summary === null, 409, __('academic_standing.curriculum_unavailable'));

        return response()->json(['data' => $summary->toArray()]);
    }

    /**
     * My Credit-Hour Summary Dashboard Card.
     *
     * @response 200 {"data":{"satisfied_ch":12,"adjusted_required_ch":20,"remaining_ch":8},"meta":{}}
     */
    public function card(Request $request, StudentDirectory $students, CreditHourSummaries $summaries): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403, __('academic_standing.student_required'));
        $summary = $summaries->forStudent($student->studentId);
        abort_if($summary === null, 409, __('academic_standing.curriculum_unavailable'));
        $total = $summary->rows['Total'];

        return response()->json(['data' => [
            'satisfied_ch' => $total->satisfiedCh,
            'adjusted_required_ch' => $total->adjustedRequiredCh,
            'remaining_ch' => $total->remainingCh,
        ], 'meta' => new \stdClass]);
    }
}
