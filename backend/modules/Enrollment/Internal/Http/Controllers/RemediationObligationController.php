<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Identity\Public\Contracts\StudentDirectory;

/** @group Enrollment */
final class RemediationObligationController
{
    /** @response 200 {"data":[{"id":1,"course_id":10,"revocation_id":3,"state":"AwaitingOffering","opportunity_offering_id":null,"checkpoint_semester_id":null,"effective_deadline":null}]} */
    public function mine(Request $request, StudentDirectory $students, RemediationObligations $obligations): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        abort_if($student === null, 403);

        return response()->json(['data' => array_map(fn ($item): array => [
            'id' => $item->id,
            'course_id' => $item->courseId,
            'revocation_id' => $item->revocationId,
            'state' => $item->state,
            'opportunity_offering_id' => $item->opportunityOfferingId,
            'checkpoint_semester_id' => $item->checkpointSemesterId,
            'effective_deadline' => $item->effectiveDeadline,
        ], $obligations->forStudent($student->studentId))]);
    }
}
