<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(DatabaseMigrations::class);

it('serializes competing screening decisions with one history and notification outcome', function (string $winner) {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(Role::StudentAffairs);
    $application = Application::factory()->underReview()->create();
    $uri = '/api/v1/applications/'.$application->id.'/screen';
    DB::disconnect();
    [$parent, $child] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    stream_set_timeout($parent, 10);
    $pid = pcntl_fork();
    if ($pid === 0) {
        fclose($parent);
        DB::disconnect();
        fread($child, 1);
        try {
            DB::reconnect();
            DB::statement("SET lock_timeout = '8s'");
            fwrite($child, DB::selectOne('select pg_backend_pid() as pid')->pid."\n");
            Sanctum::actingAs(User::findOrFail($reviewer->id));
            $request = Request::create($uri, 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
                json_encode(['action' => $winner === 'reject' ? 'approve' : 'reject', 'note' => 'Competing decision'], JSON_THROW_ON_ERROR));
            $response = app(Kernel::class)->handle($request);
            fwrite($child, json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            fwrite($child, json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR));
        }
        fclose($child);
        exit(0);
    }
    if ($pid < 1) {
        throw new RuntimeException('Could not fork the screening competitor.');
    }
    fclose($child);
    try {
        DB::reconnect();
        DB::beginTransaction();
        Sanctum::actingAs($reviewer);
        $this->postJson($uri, ['action' => $winner, 'note' => 'Winning decision'])->assertOk();
        fwrite($parent, 'G');
        $session = (int) fgets($parent);
        $waiting = false;
        $deadline = microtime(true) + 5;
        do {
            $waiting = DB::table('pg_locks')->where('pid', $session)->where('granted', false)->exists();
        } while (! $waiting && microtime(true) < $deadline);
        DB::commit();
        $result = json_decode(stream_get_contents($parent), true, flags: JSON_THROW_ON_ERROR);
    } catch (Throwable $error) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        posix_kill($pid, SIGTERM);
        throw $error;
    } finally {
        fclose($parent);
        pcntl_waitpid($pid, $status);
    }
    expect($waiting)->toBeTrue()->and($result)->toBe(['status' => 409])->and(pcntl_wexitstatus($status))->toBe(0);
    $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => $winner === 'reject' ? 'rejected' : 'under_dean_review']);
    $this->assertDatabaseCount('application_status_transitions', $winner === 'reject' ? 1 : 2);
    $this->assertDatabaseCount('notifications', $winner === 'reject' ? 1 : 0);
})->with(['reject', 'approve']);
