<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Records\Internal\Models\TranscriptRequest;
use Modules\Records\Internal\Support\RequestWorkflow;

/** @group Records */
final class CancellationController
{
    /**
     * Request Transcript cancellation.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @bodyParam reason string Optional Student explanation. Example: No longer needed
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function submit(Request $http, int $request_id, RequestWorkflow $workflow): JsonResponse
    {
        $data = $http->validate(['reason' => ['nullable', 'string', 'max:10000']]);
        $request = DB::transaction(function () use ($http, $request_id, $workflow, $data): TranscriptRequest {
            $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($request_id);
            $actor = (int) $http->user()?->getAuthIdentifier();
            abort_unless($request->user_id === $actor, 403);
            $workflow->state($request, 'Submitted', 'AwaitingPayment', 'PaymentUnderReview', 'PaymentCorrectionRequired', 'ReadyForIssuance');
            $request->cancellation_prior_status = $request->status;
            $workflow->transition($request, 'CancellationPending', 'cancellation_requested', $actor, $data['reason'] ?? null, notify: false);

            return $request;
        });

        return response()->json(['data' => $workflow->detail($request)]);
    }

    /**
     * Decide Transcript cancellation.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @bodyParam decision string required Approve or reject. Example: approve
     * @bodyParam reason string required Decision reason. Example: Full refund owed
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function review(Request $http, int $request_id, RequestWorkflow $workflow): JsonResponse
    {
        $data = $http->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'reason' => ['required', 'string', 'max:10000']]);
        $request = DB::transaction(function () use ($http, $request_id, $workflow, $data): TranscriptRequest {
            $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($request_id);
            $workflow->state($request, 'CancellationPending');
            if ($data['decision'] === 'reject') {
                $to = $request->cancellation_prior_status;
                abort_if($to === null, 409, __('records.wrong_state'));
                $request->cancellation_prior_status = null;
            } elseif ($request->verified_minor !== null) {
                $to = 'RefundPending';
                DB::table('transcript_refunds')->insert([
                    'transcript_request_id' => $request->id, 'amount_minor' => $request->verified_minor, 'created_at' => now(),
                ]);
            } else {
                $to = 'Cancelled';
            }
            $workflow->transition($request, $to, $data['decision'] === 'approve' ? 'cancellation_approved' : 'cancellation_rejected',
                (int) $http->user()?->getAuthIdentifier(), $data['reason']);

            return $request;
        });

        return response()->json(['data' => $workflow->detail($request, true)]);
    }
}
