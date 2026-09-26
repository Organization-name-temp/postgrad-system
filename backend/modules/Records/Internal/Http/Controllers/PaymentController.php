<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Records\Internal\Models\TranscriptRequest;
use Modules\Records\Internal\Support\RecordsAccess;
use Modules\Records\Internal\Support\RequestWorkflow;
use Modules\Records\Internal\Support\TranscriptFee;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @group Records */
final class PaymentController
{
    /**
     * Submit Transcript payment evidence.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @bodyParam reference string required Payment reference. Example: PAY-ONE
     * @bodyParam receipt file required Private PDF/JPEG/PNG receipt up to 10 MB.
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function submit(Request $http, int $request_id, RequestWorkflow $workflow): JsonResponse
    {
        $data = $http->validate(['reference' => ['required', 'string', 'max:200'],
            'receipt' => ['required', 'file', 'mimetypes:application/pdf,image/jpeg,image/png', 'max:10240']]);
        $path = null;
        try {
            $request = DB::transaction(function () use ($http, $request_id, $workflow, $data, &$path): TranscriptRequest {
                $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($request_id);
                $actor = (int) $http->user()?->getAuthIdentifier();
                abort_unless($request->user_id === $actor, 403);
                $workflow->state($request, 'AwaitingPayment', 'PaymentCorrectionRequired');
                $file = $http->file('receipt');
                if (! $file instanceof UploadedFile) {
                    throw ValidationException::withMessages(['receipt' => __('records.receipt_required')]);
                }
                $path = $file->store('transcript-receipts/'.$request->id, 'local');
                abort_if($path === false, 500);
                DB::table('transcript_payment_submissions')->insert([
                    'transcript_request_id' => $request->id, 'reference' => $data['reference'], 'stored_path' => $path,
                    'mime' => $file->getMimeType(), 'submitted_by' => $actor, 'created_at' => now(),
                ]);
                $workflow->transition($request, 'PaymentUnderReview', 'payment_submitted', $actor, notify: false);

                return $request;
            });
        } catch (\Throwable $failure) {
            if (is_string($path)) {
                Storage::disk('local')->delete($path);
            }
            throw $failure;
        }

        return response()->json(['data' => $workflow->detail($request)]);
    }

    /**
     * Verify or return Transcript payment.
     *
     * @urlParam request_id integer required Request id. Example: 1
     *
     * @bodyParam decision string required Verify or return. Example: verify
     * @bodyParam amount string Exact captured EGP fee when verifying. Example: 125.50
     * @bodyParam reason string Required when returning evidence. Example: Unclear receipt
     *
     * @responseFile 200 modules/Records/Public/Http/responses/request.json
     */
    public function review(Request $http, int $request_id, RequestWorkflow $workflow): JsonResponse
    {
        $data = $http->validate(['decision' => ['required', Rule::in(['verify', 'return'])],
            'amount' => ['required_if:decision,verify', 'nullable', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'reason' => ['required_if:decision,return', 'nullable', 'string', 'max:10000']]);
        $request = DB::transaction(function () use ($http, $request_id, $workflow, $data): TranscriptRequest {
            $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($request_id);
            $workflow->state($request, 'PaymentUnderReview');
            $actor = (int) $http->user()?->getAuthIdentifier();
            $submission = DB::table('transcript_payment_submissions')->where('transcript_request_id', $request->id)->orderByDesc('id')->first();
            abort_if($submission === null, 409);
            $amount = $data['decision'] === 'verify' ? TranscriptFee::minor($data['amount']) : null;
            if ($data['decision'] === 'verify' && $amount !== $request->fee_minor) {
                throw ValidationException::withMessages(['amount' => __('records.exact_payment')]);
            }
            DB::table('transcript_payment_decisions')->insert([
                'submission_id' => $submission->id, 'decision' => $data['decision'], 'reason' => $data['reason'] ?? null,
                'amount_minor' => $amount, 'actor_user_id' => $actor, 'created_at' => now(),
            ]);
            if ($data['decision'] === 'verify') {
                $request->verified_minor = $amount;
                $request->verified_by = $actor;
                $request->verified_at = now();
            }
            $workflow->transition($request, $data['decision'] === 'verify' ? 'ReadyForIssuance' : 'PaymentCorrectionRequired',
                $data['decision'] === 'verify' ? 'payment_verified' : 'payment_returned', $actor, $data['reason'] ?? null);

            return $request;
        });

        return response()->json(['data' => $workflow->detail($request, true)]);
    }

    /**
     * Private Transcript payment receipt.
     *
     * @urlParam request_id integer required Request id. Example: 1
     * @urlParam submission_id integer required Submission id. Example: 1
     *
     * @response 200 <<binary>>
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function receipt(Request $http, int $request_id, int $submission_id, RecordsAccess $access): StreamedResponse
    {
        $request = TranscriptRequest::findOrFail($request_id);
        $actor = (int) $http->user()?->getAuthIdentifier();
        abort_unless($request->user_id === $actor || $access->staff($actor), 403);
        $submission = DB::table('transcript_payment_submissions')->where('transcript_request_id', $request_id)->where('id', $submission_id)->first();
        abort_if($submission === null, 404);

        return Storage::disk('local')->response((string) $submission->stored_path, 'receipt',
            ['Content-Type' => (string) $submission->mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
