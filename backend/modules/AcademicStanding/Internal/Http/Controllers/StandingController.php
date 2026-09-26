<?php

namespace Modules\AcademicStanding\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AcademicStanding\Internal\Support\StudentStandingScope;
use Modules\AcademicStanding\Public\StandingRecords;
use Modules\Identity\Public\Contracts\StudentDirectory;

/** @group Academic Standing */
final class StandingController
{
    /**
     * My current Academic Standing and observation history.
     *
     * @response 200 {"data":{"student_id":1,"standing":"AcademicWarning","cgpa":3.2,"explanation":"effective_grade_below_c_plus","registration_ceiling_ch":null,"observations":[{"id":2,"standing":"AcademicWarning","cause_type":"grade_sheet","cause_id":9,"observed_at":"2026-09-01T10:00:00+00:00"}]}}
     */
    public function mine(Request $request, StudentDirectory $students, StandingRecords $records): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403, __('academic_standing.student_required'));

        return $this->response($student->studentId, $records);
    }

    /**
     * A scoped Student's current Academic Standing and observation history.
     *
     * @urlParam student_id integer required The Student anchor id. Example: 1
     *
     * @response 200 {"data":{"student_id":1,"standing":"GoodStanding","cgpa":3.5,"explanation":"requirements_met","registration_ceiling_ch":null,"observations":[]}}
     */
    public function show(Request $request, int $student_id, StudentDirectory $students, StudentStandingScope $scope, StandingRecords $records): JsonResponse
    {
        $student = $students->findByStudentId($student_id);
        abort_if($student === null, 404, __('academic_standing.student_missing'));
        abort_unless($scope->permits((int) $request->user()?->getAuthIdentifier(), $student), 403);

        return $this->response($student_id, $records);
    }

    /**
     * My Academic Standing Dashboard Card.
     *
     * @response 200 {"data":{"standing":"GoodStanding","cgpa":3.5,"explanation":"requirements_met"},"meta":{}}
     */
    public function card(Request $request, StudentDirectory $students, StandingRecords $records): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403, __('academic_standing.student_required'));
        $standing = $records->currentForStudent($student->studentId);

        return response()->json(['data' => $standing->toArray(), 'meta' => new \stdClass]);
    }

    private function response(int $studentId, StandingRecords $records): JsonResponse
    {
        return response()->json(['data' => [
            ...$records->currentForStudent($studentId)->toArray(),
            'observations' => $records->observationsForStudent($studentId),
        ]]);
    }
}
