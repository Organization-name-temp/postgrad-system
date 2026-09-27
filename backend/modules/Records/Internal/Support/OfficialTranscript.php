<?php

namespace Modules\Records\Internal\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Public\Contracts\Directory;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Records\Internal\Models\TranscriptRequest;

final readonly class OfficialTranscript
{
    public function __construct(private TranscriptSources $sources, private TranscriptComposer $composer,
        private StudentDirectory $students, private Directory $users, private RequestWorkflow $workflow) {}

    /** @return array<string, mixed> */
    private function content(TranscriptRequest $request): array
    {
        $this->workflow->state($request, 'ReadyForIssuance');
        abort_unless($request->approved_at !== null && $request->verified_minor === $request->fee_minor, 409, __('records.wrong_state'));
        $student = $this->students->findByStudentId($request->student_id);
        abort_unless($student !== null && $student->identity?->ready(), 409, __('records.identity_unresolved'));
        $snapshot = $this->composer->compose($student);
        abort_if($snapshot['program'] === null || $snapshot['credit_hours'] === null, 409, __('records.content_unavailable'));

        return $snapshot;
    }

    /** @param array<string, mixed> $snapshot */
    private function digest(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @return array<string, mixed> */
    public function preview(int $requestId, int $actor): array
    {
        return DB::transaction(function () use ($requestId, $actor): array {
            $this->sources->lock();
            $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($requestId);
            $snapshot = $this->content($request);
            $token = bin2hex(random_bytes(32));
            DB::table('transcript_reviews')->insert(['transcript_request_id' => $requestId,
                'token_hash' => hash('sha256', $token), 'content_hash' => $this->digest($snapshot),
                'actor_user_id' => $actor, 'created_at' => now()->toIso8601String()]);

            return ['review_token' => $token, 'snapshot' => $snapshot];
        }, 5);
    }

    /** @return array<string, mixed> */
    public function issue(int $requestId, int $actor, string $reviewToken): array
    {
        return DB::transaction(function () use ($requestId, $actor, $reviewToken): array {
            $this->sources->lock();
            $request = TranscriptRequest::query()->lockForUpdate()->findOrFail($requestId);
            $snapshot = $this->content($request);
            $review = DB::table('transcript_reviews')->where('transcript_request_id', $requestId)
                ->where('actor_user_id', $actor)->where('token_hash', hash('sha256', $reviewToken))->first();
            abort_unless($review !== null && hash_equals($review->content_hash, $this->digest($snapshot)), 409, __('records.review_changed'));
            $issuer = $this->users->findMany([$actor])[$actor] ?? null;
            abort_if($issuer === null, 409, __('records.content_unavailable'));
            $id = DB::table('transcript_issuances')->insertGetId([
                'transcript_request_id' => $requestId, 'student_id' => $request->student_id,
                'reference' => (string) Str::uuid(), 'verification_token' => bin2hex(random_bytes(32)),
                'issuer_user_id' => $actor, 'issuer_name' => $issuer->name,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'issued_at' => now()->toIso8601String(),
            ]);
            $this->workflow->transition($request, $request->delivery_method === 'digital' ? 'Completed' : 'ReadyForCollection', 'issued', $actor);

            return $this->detail($id);
        }, 5);
    }

    /** @return array<string, mixed> */
    public function detail(int $id): array
    {
        $issuance = DB::table('transcript_issuances')->where('id', $id)->first();
        abort_if($issuance === null, 404);

        $revocation = DB::table('transcript_revocations')->where('issuance_id', $id)->first();

        return ['revocation' => $revocation === null ? null : ['reason' => $revocation->reason, 'actor_user_id' => $revocation->actor_user_id, 'at' => $revocation->created_at], 'id' => $issuance->id, 'student_id' => $issuance->student_id, 'reference' => $issuance->reference,
            'status' => $revocation === null ? 'Valid' : 'Revoked', 'issuer_name' => $issuance->issuer_name, 'issued_at' => $issuance->issued_at,
            'snapshot' => json_decode($issuance->snapshot, true, 512, JSON_THROW_ON_ERROR),
            'verification_url' => '/api/v1/records/verify/'.$issuance->verification_token];
    }
}
