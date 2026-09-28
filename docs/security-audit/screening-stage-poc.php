<?php

// Standalone rollback-only HTTP-kernel probe; never migrates or seeds a database.
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

$root = dirname(__DIR__, 2);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (config('database.default') !== 'pgsql' || config('database.connections.pgsql.database') !== 'prs_test') {
    throw new RuntimeException('This probe only runs against prs_test.');
}
config(['cache.default' => 'array', 'session.driver' => 'array']);
$expected = in_array('--expect-vulnerable', $argv, true) ? 200 : 409;
$results = [];
DB::beginTransaction();
try {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($reviewer);
    foreach ([ApplicationStatus::UnderDeanReview, ApplicationStatus::InterviewCompleted, ApplicationStatus::ConditionallyAccepted, ApplicationStatus::Waitlisted] as $status) {
        $application = Application::factory()->create(['status' => $status]);
        $request = Request::create('/api/v1/applications/'.$application->id.'/screen', 'POST', [], [], [],
            ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            json_encode(['action' => 'reject', 'note' => 'AUDIT-07 stage probe'], JSON_THROW_ON_ERROR));
        $response = $app->make(HttpKernel::class)->handle($request);
        $results[] = ['from' => $status->value, 'http' => $response->getStatusCode(), 'after' => $application->refresh()->status->value,
            'transitions' => $application->statusTransitions()->count()];
    }
} finally {
    DB::rollBack();
}
echo json_encode(['expected_http' => $expected, 'rolled_back' => true, 'results' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
foreach ($results as $result) {
    if ($result['http'] !== $expected || ($expected === 409 && ($result['after'] !== $result['from'] || $result['transitions'] !== 0))) {
        exit(1);
    }
}
