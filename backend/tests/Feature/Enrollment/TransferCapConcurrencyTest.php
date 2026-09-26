<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Enrollment\Internal\Enums\TransferRequestStatus;
use Modules\Enrollment\Internal\Models\TransferDecision;
use Modules\Enrollment\Internal\Models\TransferRequest;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(DatabaseMigrations::class);

/** @return array{Student, User, list<TransferRequest>} */
function transferCapRaceWorld(): array
{
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $courses = collect([1, 2])->map(fn (int $number): Course => Course::factory()->create([
        'department_id' => $department->id,
        'code' => "CAP-RACE-{$number}",
        'lecture_ch' => 6,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 6,
    ]));
    $version = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => ['curriculum' => $courses->values()->map(fn (Course $course, int $ordinal): array => [
            'course_id' => $course->id,
            'course_code' => $course->code,
            'course_total_ch' => 6,
            'category' => CourseCategory::ProgramCore->value,
            'preparatory_requirement_role' => null,
            'ordinal' => $ordinal,
        ])->all()],
        'changed_at' => now(),
    ]);
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $student = Student::factory()->create([
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'advisor_id' => $advisor->id,
        'admitted_at' => now(),
    ]);
    $student->user->assignRole(Role::Student);
    $council = User::factory()->create();
    $council->assignRole(Role::Council);
    $requests = $courses->map(fn (Course $course): TransferRequest => TransferRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $version->id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'target_category' => CourseCategory::ProgramCore,
        'target_credit_hours' => 6,
        'external_institution' => 'Concurrent University',
        'external_course_code' => 'EXT-'.$course->id,
        'external_course_name' => 'Concurrent Course '.$course->id,
        'source_credit_hours' => '1.00',
        'external_grade' => 'Excellent',
        'document_ids' => [$course->id],
        'normalized_grade' => 'A',
        'verified_by_user_id' => $advisor->id,
        'verified_at' => now(),
        'status' => TransferRequestStatus::PendingCouncil,
        'revision' => 1,
        'submitted_at' => now(),
    ]))->all();

    return [$student, $council, $requests];
}

/** @return array{resource, resource} */
function transferRaceSockets(): array
{
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === false) {
        throw new RuntimeException('Could not create Transfer-cap race sockets.');
    }

    return $sockets;
}

it('serializes concurrent Council approvals so the target-aware lifetime cap cannot be exceeded', function () {
    [$student, $council, $requests] = transferCapRaceWorld();
    DB::disconnect();
    $pairs = [transferRaceSockets(), transferRaceSockets()];
    $pids = [];

    foreach ($requests as $index => $transferRequest) {
        [$parentSocket, $childSocket] = $pairs[$index];
        $pid = pcntl_fork();
        if ($pid === 0) {
            fclose($parentSocket);
            DB::disconnect();
            fread($childSocket, 1);
            try {
                DB::reconnect();
                Sanctum::actingAs(User::query()->findOrFail($council->id));
                $httpRequest = Request::create(
                    "/api/v1/credit-decisions/transfer-requests/{$transferRequest->id}/council-decision",
                    'POST', [], [], [],
                    ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
                    json_encode([
                        'action' => 'approve',
                        'expected_revision' => 1,
                        'action_id' => $index === 0
                            ? '10101010-1010-4010-8010-101010101010'
                            : '20202020-2020-4020-8020-202020202020',
                    ], JSON_THROW_ON_ERROR),
                );
                $kernel = app(Kernel::class);
                $response = $kernel->handle($httpRequest);
                $kernel->terminate($httpRequest, $response);
                fwrite($childSocket, json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR));
            } catch (Throwable $error) {
                fwrite($childSocket, json_encode(['status' => 0, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR));
            }
            fclose($childSocket);
            exit(0);
        }
        if ($pid < 1) {
            throw new RuntimeException('Could not fork Transfer-cap race process.');
        }
        fclose($childSocket);
        $pids[] = $pid;
    }

    foreach ($pairs as [$parentSocket]) {
        fwrite($parentSocket, '1');
    }
    $statuses = [];
    foreach ($pairs as [$parentSocket]) {
        $payload = json_decode(stream_get_contents($parentSocket), true, flags: JSON_THROW_ON_ERROR);
        $statuses[] = $payload['status'];
        fclose($parentSocket);
    }
    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $childStatus);
        expect(pcntl_wexitstatus($childStatus))->toBe(0);
    }
    DB::reconnect();
    sort($statuses);

    expect($statuses)->toBe([200, 409])
        ->and(TransferDecision::query()->where('student_id', $student->id)->count())->toBe(1)
        ->and((int) TransferDecision::query()->where('student_id', $student->id)->sum('target_credit_hours'))->toBe(6);
});
