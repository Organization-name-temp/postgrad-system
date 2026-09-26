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
        if ($staff || $request->approved_at !== null) {
            $data['instructions_en'] = $request->instructions_en;
            $data['instructions_ar'] = $request->instructions_ar;
        }

        return $data;
    }
}
