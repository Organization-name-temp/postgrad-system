<?php

namespace Modules\Records\Internal\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Knuckles\Scribe\Attributes\ResponseField;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Records\Internal\Models\TranscriptRequest;
use Modules\Records\Internal\Support\TranscriptFee;

/** @group Records */
final class FeeController
{
    /**
     * Transcript service availability.
     *
     * @response 200 {"data":{"available":true,"fee":"125.50","currency":"EGP","open_request_id":1}}
     */
    #[ResponseField('data.fee', 'string', nullable: true)]
    #[ResponseField('data.open_request_id', 'integer', nullable: true)]
    public function availability(Request $request, TranscriptFee $fee, StudentDirectory $students): JsonResponse
    {
        $student = $students->find((int) $request->user()?->getAuthIdentifier());
        $data = $fee->read();
        $data['open_request_id'] = $student === null ? null : TranscriptRequest::query()->where('student_id', $student->studentId)
            ->whereNotIn('status', ['Completed', 'Rejected', 'Cancelled'])->value('id');

        return response()->json(['data' => $data]);
    }

    /**
     * Transcript fee configuration.
     *
     * @response 200 {"data":{"available":true,"fee":"125.50","currency":"EGP","instructions_en":"Fictional learning instructions","instructions_ar":"تعليمات تعليمية وهمية"}}
     */
    #[ResponseField('data.fee', 'string', nullable: true)]
    public function show(TranscriptFee $fee): JsonResponse
    {
        return response()->json(['data' => $fee->read(true)]);
    }

    /**
     * Configure Transcript fee.
     *
     * @bodyParam fee string required Positive EGP amount, at most two decimal places. Example: 125.50
     * @bodyParam instructions_en string required English payment instructions. Example: Fictional learning instructions
     * @bodyParam instructions_ar string required Arabic payment instructions. Example: تعليمات تعليمية وهمية
     *
     * @response 200 {"data":{"available":true,"fee":"125.50","currency":"EGP","instructions_en":"Fictional learning instructions","instructions_ar":"تعليمات تعليمية وهمية"}}
     */
    public function update(Request $request, TranscriptFee $fee, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'fee' => ['required', 'string', 'regex:/^\d{1,7}(\.\d{1,2})?$/'],
            'instructions_en' => ['required', 'string', 'max:10000'],
            'instructions_ar' => ['required', 'string', 'max:10000'],
        ]);
        $minor = TranscriptFee::minor($data['fee']);
        if ($minor <= 0) {
            throw ValidationException::withMessages(['fee' => __('records.positive_fee')]);
        }
        DB::transaction(function () use ($data, $minor, $request, $audit): void {
            DB::table('transcript_fee')->upsert([
                'id' => 1, 'fee_minor' => $minor, 'instructions_en' => $data['instructions_en'],
                'instructions_ar' => $data['instructions_ar'], 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
            ], ['id'], ['fee_minor', 'instructions_en', 'instructions_ar', 'updated_at']);
            $audit->record(action: 'records.fee_updated', actorUserId: (int) $request->user()?->getAuthIdentifier(),
                metadata: ['fee_minor' => $minor, 'currency' => 'EGP'], subjectType: 'transcript_fee', subjectId: '1');
        });

        return response()->json(['data' => $fee->read(true)]);
    }
}
