<?php

namespace Modules\Enrollment\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Enrollment\Internal\Http\Resources\ExemptionDecisionResource;
use Modules\Enrollment\Internal\Http\Resources\TransferDecisionResource;
use Modules\Enrollment\Internal\Http\Resources\WaiverDecisionResource;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Support\CreditDecisionRevoker;

/** @group Credit Decisions */
class CreditDecisionRevocationController
{
    /**
     * Revoke an Exemption decision.
     *
     * @urlParam decision_id integer required The Exemption decision ID. Example: 1
     *
     * @bodyParam reason string required The attributed correction reason. Example: Corrected admission evidence.
     *
     * @response 200 {"data":{"id":1,"student_id":4,"target_course_id":8,"target_course_code":"PRE401","credit_hours":1,"source":"manual","effective":false,"revocation":{"id":1,"revoked_by_user_id":6,"reason":"Corrected admission evidence.","created_at":"2026-09-24T10:00:00+00:00"}}}
     */
    public function exemption(Request $request, ExemptionDecision $decision, CreditDecisionRevoker $revoker): JsonResponse
    {
        $reason = $this->reason($request);
        $result = $revoker->exemption($decision, (int) $request->user()?->getAuthIdentifier(), $reason);

        return (new ExemptionDecisionResource($result))->response();
    }

    /**
     * Revoke a Transfer decision.
     *
     * @urlParam decision_id integer required The Transfer decision ID. Example: 1
     *
     * @bodyParam reason string required The attributed correction reason. Example: The source transcript was corrected.
     *
     * @response 200 {"data":{"id":1,"student_id":4,"target_course_id":5,"target_course_code":"CSE501","target_credit_hours":3,"normalized_grade":"A","effective":false,"revocation":{"id":1,"revoked_by_user_id":9,"reason":"The source transcript was corrected.","created_at":"2026-09-24T10:00:00+00:00"}}}
     */
    public function transfer(Request $request, TransferDecision $decision, CreditDecisionRevoker $revoker): JsonResponse
    {
        $reason = $this->reason($request);
        $result = $revoker->transfer($decision, (int) $request->user()?->getAuthIdentifier(), $reason);

        return (new TransferDecisionResource($result))->response();
    }

    /**
     * Revoke a Waiver decision.
     *
     * @urlParam decision_id integer required The Waiver decision ID. Example: 1
     *
     * @bodyParam reason string required The attributed correction reason. Example: Coverage verification was corrected.
     *
     * @response 200 {"data":{"id":1,"student_id":4,"target_course_id":5,"target_course_code":"CSE511","credit_hours":3,"effective":false,"revocation":{"id":1,"revoked_by_user_id":9,"reason":"Coverage verification was corrected.","created_at":"2026-09-24T10:00:00+00:00"}}}
     */
    public function waiver(Request $request, WaiverDecision $decision, CreditDecisionRevoker $revoker): JsonResponse
    {
        $reason = $this->reason($request);
        $result = $revoker->waiver($decision, (int) $request->user()?->getAuthIdentifier(), $reason);

        return (new WaiverDecisionResource($result))->response();
    }

    private function reason(Request $request): string
    {
        /** @var array{reason: string} $validated */
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        return $validated['reason'];
    }
}
