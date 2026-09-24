<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Enrollment\Internal\Http\Resources\TransferDecisionResource;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Identity\Public\Contracts\StudentDirectory;

/** @group Transfers */
class TransferDecisionController
{
    /**
     * My effective Transfer decisions.
     *
     * @response 200 {"data": [{"id": 1, "student_id": 4, "curriculum_version_id": 7, "target_course_id": 5, "target_course_code": "CSE501", "target_category": "ProgramCore", "target_credit_hours": 3, "external_institution": "Kyoto University", "external_course_code": "CS501", "external_course_name": "Algorithms", "source_credit_hours": "4.00", "external_grade": "Excellent", "document_ids": ["01ARZ3NDEKTSV4RRFFQ69G5FAV"], "normalized_grade": "A", "verified_by_user_id": 8, "verified_at": "2026-09-01T10:00:00+00:00", "approved_by_user_id": 9, "approved_at": "2026-09-02T10:00:00+00:00", "transfer_request_id": 1}]}
     */
    public function index(Request $request, StudentDirectory $students): AnonymousResourceCollection
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);

        return TransferDecisionResource::collection(
            TransferDecision::query()->where('student_id', $student->studentId)->orderBy('id')->get(),
        );
    }
}
