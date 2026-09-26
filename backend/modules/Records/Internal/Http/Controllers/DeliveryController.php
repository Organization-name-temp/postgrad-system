<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Modules\Records\Internal\Models\TranscriptRequest;
use Modules\Records\Internal\Support\OfficialTranscript;
use Modules\Records\Internal\Support\RequestWorkflow;

/** @group Records */
final class DeliveryController
{
    /**
     * Record collection handover.
     *
     * @urlParam request_id integer required Example: 1
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function collect(Request $http, int $request_id, RequestWorkflow $workflow): JsonResponse
    {
        $data = DB::transaction(function () use ($http, $request_id, $workflow): array {
            $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($request_id);
            $workflow->state($request, 'ReadyForCollection');
            abort_unless($request->delivery_method === 'collection', 409, __('records.wrong_state'));
            $workflow->transition($request, 'Completed', 'collected', (int) $http->user()?->getAuthIdentifier());

            return $workflow->detail($request, true);
        }, 5);

        return response()->json(['data' => $data]);
    }

    /**
     * Revoke official copy validity.
     *
     * @urlParam issuance_id integer required Example: 1
     *
     * @bodyParam reason string required Revocation reason. Example: Corrected content required
     *
     * @responseFile 200 modules/Records/Public/Http/responses/issuance.json
     */
    public function revoke(Request $http, int $issuance_id, OfficialTranscript $official, AuditLogger $audit, Notifier $notifier): JsonResponse
    {
        $input = $http->validate(['reason' => ['required', 'string', 'max:10000']]);
        $actor = (int) $http->user()?->getAuthIdentifier();
        $detail = DB::transaction(function () use ($input, $actor, $issuance_id, $official, $audit, $notifier): array {
            $issuance = DB::table('transcript_issuances')->where('id', $issuance_id)->lockForUpdate()->first();
            abort_if($issuance === null, 404);
            abort_if(DB::table('transcript_revocations')->where('issuance_id', $issuance_id)->exists(), 409, __('records.wrong_state'));
            DB::table('transcript_revocations')->insert(['issuance_id' => $issuance_id, 'reason' => $input['reason'], 'actor_user_id' => $actor, 'created_at' => now()->toIso8601String()]);
            $audit->record(action: 'records.revoked', actorUserId: $actor, subjectType: 'transcript_issuance', subjectId: (string) $issuance_id);
            $request = TranscriptRequest::query()->findOrFail($issuance->transcript_request_id);
            $notifier->notifyOnce($request->user_id, NotificationType::RecordsRequestChanged, 'records-revocation:'.$issuance_id,
                ['request_id' => $request->id, 'status' => 'Revoked', 'action' => 'revoked']);

            return $official->detail($issuance_id);
        }, 5);

        return response()->json(['data' => $detail]);
    }
}
