<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\TranscriptReadGuard;

uses(DatabaseMigrations::class);

it('waits for a real contributing owner writer and refuses certification of the old reviewed content', function (string $source) {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);
    $sheet = GradeSheet::factory()->create(['course_offering_id' => $enrollment->course_offering_id, 'status' => 'Finalized', 'reviewed_at' => now()]);
    $grade = Grade::factory()->withMark(80)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);
    $guardClass = match ($source) {
        'identity' => TranscriptReadGuard::class,
        'catalog' => Modules\Catalog\Public\TranscriptReadGuard::class,
        'grading' => Modules\Grading\Public\TranscriptReadGuard::class,
    };
    $actorId = auth()->id();
    $token = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data.review_token');
    DB::disconnect();
    [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $pid = pcntl_fork();
    if ($pid === 0) {
        fclose($parent);
        DB::disconnect();
        fread($child, 1);
        try {
            DB::reconnect();
            $guard = app($guardClass);
            app()->instance($guardClass, new class($guard, $child) implements Modules\Catalog\Public\TranscriptReadGuard, Modules\Grading\Public\TranscriptReadGuard, TranscriptReadGuard
            {
                public function __construct(private TranscriptReadGuard|Modules\Catalog\Public\TranscriptReadGuard|Modules\Grading\Public\TranscriptReadGuard $guard, private mixed $signal) {}

                public function lock(): void
                {
                    fwrite($this->signal, 'L');
                    $this->guard->lock();
                }
            });
            Sanctum::actingAs(User::query()->findOrFail($actorId));
            $request = Request::create("/api/v1/records/requests/{$id}/issue", 'POST', [], [], [],
                ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode(['review_token' => $token], JSON_THROW_ON_ERROR));
            $kernel = app(Kernel::class);
            $response = $kernel->handle($request);
            fwrite($child, json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            fwrite($child, json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR));
        }
        fclose($child);
        exit(0);
    }
    expect($pid)->toBeGreaterThan(0);
    fclose($child);
    DB::reconnect();
    DB::beginTransaction();
    match ($source) {
        'identity' => DB::table('students')->where('id', $student->id)->update(['name_ar' => 'هوية معدلة قبل الإصدار']),
        'catalog' => DB::table('programs')->where('id', $student->program_id)->update(['name_ar' => 'اسم برنامج معدل']),
        'grading' => DB::table('grades')->where('id', $grade->id)->update(Grade::derivedFrom(95)),
    };
    $table = match ($source) {
        'identity' => 'students', 'catalog' => 'programs', 'grading' => 'grades'
    };
    fwrite($parent, 'G');
    expect(fread($parent, 1))->toBe('L');
    // Observe the actual ungranted table lock, rather than inferring a race from a sleep.
    $waiting = false;
    $deadline = microtime(true) + 5;
    do {
        $waiting = DB::table('pg_locks')->whereRaw('relation = ?::regclass', [$table])
            ->where('mode', 'ShareLock')->where('granted', false)->exists();
    } while (! $waiting && microtime(true) < $deadline);
    DB::commit();
    $response = json_decode(stream_get_contents($parent), true, flags: JSON_THROW_ON_ERROR);
    fclose($parent);
    pcntl_waitpid($pid, $status);
    expect($waiting)->toBeTrue()->and($response)->toBe(['status' => 409])->and(pcntl_wexitstatus($status))->toBe(0);
    $this->assertDatabaseCount('transcript_issuances', 0);
    $this->assertDatabaseHas('transcript_requests', ['id' => $id, 'status' => 'ReadyForIssuance']);
})->with(['identity', 'catalog', 'grading']);

/**
 * Run competing HTTP operations through independent PostgreSQL sessions.
 * The winning HTTP operation stays uncommitted until the losing session is observed waiting.
 *
 * @param  array<string, mixed>  $payload
 * @return array{waiting: bool, response: array<string, mixed>}
 */
function competingRecordsHttp(int $actorId, string $uri, array $payload, Closure $winner, array $files = []): array
{
    DB::disconnect();
    [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $pid = pcntl_fork();
    if ($pid === 0) {
        fclose($parent);
        DB::disconnect();
        fread($child, 1);
        try {
            DB::reconnect();
            $sessionId = DB::selectOne('select pg_backend_pid() as pid')->pid;
            fwrite($child, $sessionId."\n");
            Sanctum::actingAs(User::query()->findOrFail($actorId));
            $request = Request::create($uri, 'POST', [], [], $files, ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode($payload, JSON_THROW_ON_ERROR));
            $response = app(Kernel::class)->handle($request);
            fwrite($child, json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            fwrite($child, json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR));
        }
        fclose($child);
        exit(0);
    }
    if ($pid < 1) {
        throw new RuntimeException('Could not fork Records HTTP competitor.');
    }
    fclose($child);
    DB::reconnect();
    DB::beginTransaction();
    try {
        $winner();
        fwrite($parent, 'G');
        $sessionId = (int) fgets($parent);
        $waiting = false;
        $deadline = microtime(true) + 5;
        do {
            $waiting = DB::table('pg_locks')->where('pid', $sessionId)->where('granted', false)->exists();
        } while (! $waiting && microtime(true) < $deadline);
        DB::commit();
    } catch (Throwable $error) {
        DB::rollBack();
        posix_kill($pid, SIGTERM);
        pcntl_waitpid($pid, $status);
        throw $error;
    }
    $response = json_decode(stream_get_contents($parent), true, flags: JSON_THROW_ON_ERROR);
    fclose($parent);
    pcntl_waitpid($pid, $status);
    expect(pcntl_wexitstatus($status))->toBe(0);

    return ['waiting' => $waiting, 'response' => $response];
}

it('serializes two HTTP submissions under the owner guard and commits only one open request', function () {
    $student = recordsStudent();
    configureRecordsFee();
    Sanctum::actingAs($student->user);
    $result = competingRecordsHttp($student->user_id, '/api/v1/records/requests', ['delivery_method' => 'collection'], function () {
        test()->postJson('/api/v1/records/requests', ['delivery_method' => 'digital'])->assertCreated();
    });
    expect($result)->toBe(['waiting' => true, 'response' => ['status' => 409]]);
    $this->assertDatabaseCount('transcript_requests', 1);
    $this->assertDatabaseCount('transcript_request_transitions', 1);
});

it('serializes duplicate certification and both issuance-cancellation orderings', function (string $winner) {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $staff = auth()->user();
    $token = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data.review_token');
    $issue = "/api/v1/records/requests/{$id}/issue";
    $cancel = "/api/v1/records/requests/{$id}/cancellation";
    $issueData = ['review_token' => $token];
    $loserActor = $winner === 'issue' ? $student->user_id : $staff->id;
    $loserUri = $winner === 'issue' ? $cancel : $issue;
    $loserData = $winner === 'issue' ? [] : $issueData;
    $result = competingRecordsHttp($loserActor, $loserUri, $loserData, function () use ($winner, $student, $staff, $issue, $cancel, $issueData) {
        if ($winner === 'cancellation') {
            Sanctum::actingAs($student->user);
            test()->postJson($cancel)->assertOk();
        } else {
            Sanctum::actingAs($staff);
            test()->postJson($issue, $issueData)->assertCreated();
        }
    });
    expect($result)->toBe(['waiting' => true, 'response' => ['status' => 409]]);
    $this->assertDatabaseCount('transcript_issuances', $winner === 'cancellation' ? 0 : 1);
    expect(DB::table('audit_log')->where('action', 'records.issued')->count())->toBe($winner === 'cancellation' ? 0 : 1);
    $this->assertDatabaseHas('transcript_requests', ['id' => $id, 'status' => $winner === 'cancellation' ? 'CancellationPending' : 'Completed']);
})->with(['issue', 'cancellation', 'duplicateIssue']);

it('verification waiting behind cancellation cannot record payment or remove the pause', function () {
    [$student, $id] = transcriptAtStage('PaymentUnderReview');
    $staff = actingAsRole(Role::StudentAffairs);
    $result = competingRecordsHttp($staff->id, "/api/v1/records/requests/{$id}/payment-review", ['decision' => 'verify', 'amount' => '125.50'], function () use ($student, $id) {
        Sanctum::actingAs($student->user);
        test()->postJson("/api/v1/records/requests/{$id}/cancellation")->assertOk();
    });
    expect($result)->toBe(['waiting' => true, 'response' => ['status' => 409]]);
    $this->assertDatabaseCount('transcript_payment_decisions', 0);
    $this->assertDatabaseHas('transcript_requests', ['id' => $id, 'status' => 'CancellationPending', 'verified_minor' => null]);
});

it('commits one full refund when two documented HTTP settlements compete', function () {
    [$student, $id] = paidCancelledTranscript();
    $staff = auth()->user();
    $receipt = UploadedFile::fake()->create('refund.pdf', 10, 'application/pdf');
    $data = ['amount' => '125.50', 'reference' => 'REFUND-RACE'];
    $uri = "/api/v1/records/requests/{$id}/refund";
    $result = competingRecordsHttp($staff->id, $uri, $data, function () use ($uri, $data, $receipt) {
        test()->postJson($uri, [...$data, 'receipt' => $receipt])->assertOk();
    }, ['receipt' => $receipt]);
    expect($result)->toBe(['waiting' => true, 'response' => ['status' => 409]]);
    expect(DB::table('audit_log')->where('action', 'records.refund_completed')->count())->toBe(1);
    expect(DB::table('transcript_request_transitions')->where('action', 'refund_completed')->count())->toBe(1);
    expect(Storage::disk('local')->files('transcript-refunds/'.$id))->toHaveCount(1);
    $this->assertDatabaseHas('transcript_requests', ['id' => $id, 'status' => 'Cancelled']);
});

it('commits one collection handover when duplicate HTTP confirmations compete', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    DB::table('transcript_requests')->where('id', $id)->update(['delivery_method' => 'collection']);
    $staff = auth()->user();
    $token = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data.review_token');
    $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $token])->assertCreated();
    $uri = "/api/v1/records/requests/{$id}/collection";
    $result = competingRecordsHttp($staff->id, $uri, [], function () use ($uri) {
        test()->postJson($uri)->assertOk();
    });
    expect($result)->toBe(['waiting' => true, 'response' => ['status' => 409]]);
    expect(DB::table('audit_log')->where('action', 'records.collected')->count())->toBe(1);
    expect(DB::table('transcript_request_transitions')->where('action', 'collected')->count())->toBe(1);
    $this->assertDatabaseHas('transcript_requests', ['id' => $id, 'status' => 'Completed']);
});

it('retains verification that commits before cancellation and creates the full refund obligation', function () {
    [$student, $id] = transcriptAtStage('PaymentUnderReview');
    $staff = actingAsRole(Role::StudentAffairs);
    $result = competingRecordsHttp($student->user_id, "/api/v1/records/requests/{$id}/cancellation", [], function () use ($id) {
        test()->postJson("/api/v1/records/requests/{$id}/payment-review", ['decision' => 'verify', 'amount' => '125.50'])->assertOk();
    });
    expect($result)->toBe(['waiting' => true, 'response' => ['status' => 200]]);
    $this->assertDatabaseHas('transcript_requests', ['id' => $id, 'status' => 'CancellationPending', 'cancellation_prior_status' => 'ReadyForIssuance', 'verified_minor' => 12550]);
    Sanctum::actingAs($staff);
    $this->postJson("/api/v1/records/requests/{$id}/cancellation-review", ['decision' => 'approve', 'reason' => 'Cancel verified service'])->assertOk()
        ->assertJsonPath('data.status', 'RefundPending')->assertJsonPath('data.refund.amount', '125.50');
    $this->assertDatabaseCount('transcript_refunds', 1);
});
