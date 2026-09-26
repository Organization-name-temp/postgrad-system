<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Records\Internal\Models\TranscriptRequest;
use Modules\Records\Internal\Support\RecordsAccess;
use Modules\Records\Internal\Support\RequestWorkflow;
use Modules\Records\Internal\Support\TranscriptFee;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @group Records */
final class RefundController
{
    /**
     * Complete full manual Transcript refund.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @bodyParam amount string required Full verified EGP payment. Example: 125.50
     * @bodyParam reference string required External manual refund reference. Example: REF-ONE
     * @bodyParam receipt file required Private PDF/JPEG/PNG refund receipt up to 10 MB.
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function complete(Request $http, int $request_id, RequestWorkflow $workflow): JsonResponse
    {
        $data = $http->validate([
            'amount' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'reference' => ['required', 'string', 'max:200'],
            'receipt' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240'],
        ]);
        $path = null;
        try {
            $request = DB::transaction(function () use ($http, $request_id, $data, $workflow, &$path): TranscriptRequest {
                $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($request_id);
                $workflow->state($request, 'RefundPending');
                $refund = DB::table('transcript_refunds')->where('transcript_request_id', $request_id)->lockForUpdate()->first();
                abort_if($refund === null || $refund->completed_at !== null, 409, __('records.wrong_state'));
                $amount = TranscriptFee::minor($data['amount']);
                if ($amount !== (int) $refund->amount_minor || $amount !== $request->verified_minor) {
                    throw ValidationException::withMessages(['amount' => __('records.full_refund')]);
                }
                $file = $http->file('receipt');
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['receipt' => __('records.receipt_required')]);
                }
                $path = $file->store('transcript-refunds/'.$request_id, 'local');
                abort_if($path === false, 500);
                $actor = (int) $http->user()?->getAuthIdentifier();
                DB::table('transcript_refunds')->where('id', $refund->id)->update([
                    'reference' => $data['reference'], 'stored_path' => $path, 'mime' => $file->getMimeType(),
                    'completed_by' => $actor, 'completed_at' => now(),
                ]);
                $workflow->transition($request, 'Cancelled', 'refund_completed', $actor);

                return $request;
            });
        } catch (\Throwable $failure) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $failure;
        }

        return response()->json(['data' => $workflow->detail($request, true)]);
    }

    /**
     * Private Transcript refund receipt.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @response 200 <<binary>>
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function receipt(Request $http, int $request_id, RecordsAccess $access): StreamedResponse
    {
        $request = TranscriptRequest::findOrFail($request_id);
        $actor = (int) $http->user()?->getAuthIdentifier();
        abort_unless($request->user_id === $actor || $access->staff($actor), 403);
        $refund = DB::table('transcript_refunds')->where('transcript_request_id', $request_id)->whereNotNull('completed_at')->first();
        abort_if($refund === null, 404);

        return Storage::disk('local')->response((string) $refund->stored_path, 'refund-receipt',
            ['Content-Type' => (string) $refund->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
