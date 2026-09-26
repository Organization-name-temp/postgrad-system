<?php

namespace Modules\Records\Internal\Support;

use Illuminate\Support\Facades\DB;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;
use Modules\Records\Internal\Models\TranscriptRequest;

final readonly class RequestWorkflow
{
    public function __construct(private AuditLogger $audit, private Notifier $notifier) {}

    public function transition(TranscriptRequest $request, string $to, string $action, int $actor, ?string $reason = null, bool $notify = true): void
    {
        $from = $request->exists ? $request->status : null;
        $request->status = $to;
        $request->save();
        $transition = DB::table('transcript_request_transitions')->insertGetId([
            'transcript_request_id' => $request->id, 'from_status' => $from,
            'to_status' => $to, 'action' => $action, 'reason' => $reason, 'actor_user_id' => $actor, 'created_at' => now(),
        ]);
        $this->audit->record(action: 'records.'.$action, actorUserId: $actor,
            metadata: ['from_status' => $from, 'to_status' => $to, 'transition_id' => $transition],
            subjectType: 'transcript_request', subjectId: (string) $request->id);
        if ($notify) {
            $this->notifier->notifyOnce($request->user_id, NotificationType::RecordsRequestChanged,
                'records-transition:'.$transition, ['request_id' => $request->id, 'status' => $to, 'action' => $action]);
        }
    }

    public function state(TranscriptRequest $request, string ...$allowed): void
    {
        abort_unless(in_array($request->status, $allowed, true), 409, __('records.wrong_state'));
    }

    /** @return array<string, mixed> */
    public function detail(TranscriptRequest $request, bool $staff = false): array
    {
        $data = ['id' => $request->id, 'student_id' => $request->student_id, 'delivery_method' => $request->delivery_method,
            'fee' => TranscriptFee::format($request->fee_minor), 'currency' => 'EGP', 'status' => $request->status,
            'created_at' => $request->created_at->toIso8601String(),
            'transitions' => DB::table('transcript_request_transitions')->where('transcript_request_id', $request->id)->orderBy('id')->get()->map(fn ($t): array => [
                'id' => $t->id, 'from_status' => $t->from_status, 'to_status' => $t->to_status,
                'action' => $t->action, 'reason' => $t->reason, 'actor_user_id' => $t->actor_user_id, 'at' => $t->created_at,
            ])->all(),
        ];
        $data['payments'] = DB::table('transcript_payment_submissions')->where('transcript_request_id', $request->id)->orderBy('id')->get()->map(function ($submission) use ($request): array {
            $decision = DB::table('transcript_payment_decisions')->where('submission_id', $submission->id)->first();

            return ['id' => $submission->id, 'reference' => $submission->reference, 'submitted_by' => $submission->submitted_by,
                'submitted_at' => $submission->created_at, 'receipt_url' => '/api/v1/records/requests/'.$request->id.'/payments/'.$submission->id.'/receipt',
                'decision' => $decision === null ? null : ['decision' => $decision->decision, 'reason' => $decision->reason,
                    'actor_user_id' => $decision->actor_user_id, 'at' => $decision->created_at]];
        })->all();
        $data['verified_payment'] = $request->verified_minor === null ? null : [
            'amount' => TranscriptFee::format($request->verified_minor), 'currency' => 'EGP',
            'verified_by' => $request->verified_by, 'verified_at' => $request->verified_at?->toIso8601String(),
        ];
        $data['cancellation_prior_status'] = $request->cancellation_prior_status;
        $refund = DB::table('transcript_refunds')->where('transcript_request_id', $request->id)->first();
        $data['refund'] = $refund === null ? null : [
            'id' => $refund->id, 'amount' => TranscriptFee::format((int) $refund->amount_minor), 'currency' => 'EGP',
            'created_at' => $refund->created_at, 'reference' => $refund->reference,
            'completed_by' => $refund->completed_by, 'completed_at' => $refund->completed_at,
            'receipt_url' => $refund->completed_at === null ? null : '/api/v1/records/requests/'.$request->id.'/refund/receipt',
        ];
        if ($staff || $request->approved_at !== null) {
            $data['instructions_en'] = $request->instructions_en;
            $data['instructions_ar'] = $request->instructions_ar;
        }

        return $data;
    }
}
