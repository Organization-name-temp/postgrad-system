<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Enrollment\Public\RemediationObligations;
use Modules\Identity\Public\Contracts\StudentDirectory;

/** @group Enrollment */
final class RemediationObligationController
{
    /** @response 200 {"data":[{"id":1,"course_id":10,"revocation_id":3,"state":"AwaitingOffering","opportunity_offering_id":null,"checkpoint_semester_id":null,"effective_deadline":null,"transitions":[{"id":1,"from_state":null,"to_state":"AwaitingOffering","evidence":{"revocation_id":3},"created_at":"2026-09-25T12:00:00+00:00"}]}]} */
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
            'transitions' => array_map(fn ($transition): array => [
                'id' => $transition->id,
                'from_state' => $transition->fromState,
                'to_state' => $transition->toState,
                'evidence' => $transition->evidence,
                'created_at' => $transition->createdAt,
            ], $item->transitions),
        ], $obligations->forStudent($student->studentId))]);
    }
}
