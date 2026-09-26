<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\TranscriptReadGuard;

uses(DatabaseMigrations::class);

it('waits for a real identity writer and refuses certification of the old reviewed content', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
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
            $guard = app(TranscriptReadGuard::class);
            app()->instance(TranscriptReadGuard::class, new class($guard, $child) implements TranscriptReadGuard
            {
                public function __construct(private TranscriptReadGuard $guard, private mixed $signal) {}

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
    DB::table('students')->where('id', $student->id)->update(['name_ar' => 'هوية معدلة قبل الإصدار']);
    fwrite($parent, 'G');
    expect(fread($parent, 1))->toBe('L');
    // Observe the actual ungranted table lock, rather than inferring a race from a sleep.
    $waiting = false;
    $deadline = microtime(true) + 5;
    do {
        $waiting = DB::table('pg_locks')->whereRaw("relation = 'students'::regclass")
            ->where('mode', 'ShareLock')->where('granted', false)->exists();
    } while (! $waiting && microtime(true) < $deadline);
    DB::commit();
    $response = json_decode(stream_get_contents($parent), true, flags: JSON_THROW_ON_ERROR);
    fclose($parent);
    pcntl_waitpid($pid, $status);
    expect($waiting)->toBeTrue()->and($response)->toBe(['status' => 409])->and(pcntl_wexitstatus($status))->toBe(0);
    $this->assertDatabaseCount('transcript_issuances', 0);
    $this->assertDatabaseHas('transcript_requests', ['id' => $id, 'status' => 'ReadyForIssuance']);
});
