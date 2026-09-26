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
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\ExemptionRequestStatus;
use Modules\Enrollment\Internal\Models\AutomaticExemptionEvidence;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Models\ExemptionRequestTransition;
use Modules\Enrollment\Internal\Support\AutomaticExemptionApplicator;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentGuard;
use Modules\Identity\Public\Contracts\StudentLifecycleWriter;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\Enums\StudentLifecycleStatus;
use Modules\Identity\Public\StudentSummary;

uses(DatabaseMigrations::class);

/** @return array{Student, Course, User} */
function lifecycleConcurrencyWorld(): array
{
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $course = Course::factory()->create([
        'department_id' => $department->id,
        'code' => 'PRE-RACE',
        'lecture_ch' => 1,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 1,
    ]);
    $version = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => ['curriculum' => [[
            'course_id' => $course->id,
            'course_code' => $course->code,
            'course_total_ch' => 1,
            'category' => CourseCategory::Preparatory->value,
            'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage->value,
            'ordinal' => 0,
        ]]],
        'changed_at' => now(),
    ]);
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $student = Student::factory()->create([
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'advisor_id' => $advisor->id,
    ]);
    $student->user->assignRole(Role::Student);
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    DepartmentHeadAssignment::factory()->create([
        'user_id' => $head->id,
        'department_id' => $department->id,
    ]);

    return [$student, $course, $head];
}

function pendingHeadRaceRequest(Student $student, Course $course): ExemptionRequest
{
    $request = ExemptionRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage,
        'credit_hours' => 1,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
        'status' => ExemptionRequestStatus::PendingDepartmentHead,
        'revision' => 1,
        'submitted_at' => now(),
    ]);
    ExemptionRequestTransition::query()->create([
        'exemption_request_id' => $request->id,
        'from_status' => ExemptionRequestStatus::PendingAdvisor,
        'to_status' => ExemptionRequestStatus::PendingDepartmentHead,
        'action' => 'recommended_by_advisor',
        'actor_user_id' => $student->advisor_id,
        'revision' => 1,
        'evidence_snapshot' => $request->evidence,
        'created_at' => now(),
    ]);

    return $request;
}

/**
 * Hold a real lifecycle write open while another process drives an HTTP mutation.
 *
 * @param  array<string, mixed>  $payload
 * @return array{ready: string, guard: string, completed_while_locked: bool, exit: int, response: array<string, mixed>}
 */
function raceHttpBehindCommittedTermination(Student $student, int $actorUserId, string $method, string $uri, array $payload): array
{
    // Never fork an open libpq/TLS socket: parent and child must establish
    // independent PostgreSQL sessions after the process boundary.
    DB::disconnect();
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($sockets === false) {
        throw new RuntimeException('Could not create the lifecycle-race synchronization socket.');
    }
    [$parentSocket, $childSocket] = $sockets;

    $pid = pcntl_fork();
    if ($pid === 0) {
        fclose($parentSocket);
        DB::disconnect();
        fread($childSocket, 1);

        try {
            DB::reconnect();
            $guard = app(StudentGuard::class);
            app()->instance(StudentGuard::class, new class($guard, $childSocket) implements StudentGuard
            {
                /** @param resource $signal */
                public function __construct(
                    private readonly StudentGuard $guard,
                    private $signal,
                ) {}

                public function lock(int $studentId): ?StudentSummary
                {
                    fwrite($this->signal, 'L');

                    return $this->guard->lock($studentId);
                }
            });
            Sanctum::actingAs(User::query()->findOrFail($actorUserId));
            fwrite($childSocket, 'R');
            $request = Request::create(
                $uri,
                $method,
                [],
                [],
                [],
                ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
                json_encode($payload, JSON_THROW_ON_ERROR),
            );
            $kernel = app(Kernel::class);
            $response = $kernel->handle($request);
            $kernel->terminate($request, $response);
            fwrite($childSocket, json_encode([
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
            ], JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            fwrite($childSocket, json_encode(['status' => 0, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR));
        }
        fclose($childSocket);
        exit(0);
    }

    if ($pid < 1) {
        throw new RuntimeException('Could not fork the lifecycle-race HTTP process.');
    }
    fclose($childSocket);
    DB::reconnect();
    DB::beginTransaction();
    app(StudentLifecycleWriter::class)->terminate((int) $student->id);
    fwrite($parentSocket, '1');
    $readySignal = fread($parentSocket, 1);
    $read = [$parentSocket];
    $write = null;
    $except = null;
    $secondSignalReady = stream_select($read, $write, $except, 5) === 1;
    $guardSignal = $secondSignalReady ? fread($parentSocket, 1) : '';
    $completedWhileLifecycleLockWasHeld = true;
    if ($guardSignal === 'L') {
        $read = [$parentSocket];
        $write = null;
        $except = null;
        $completedWhileLifecycleLockWasHeld = stream_select($read, $write, $except, 0, 250_000) === 1;
    }
    DB::commit();

    $resultJson = ($guardSignal === 'L' ? '' : $guardSignal).stream_get_contents($parentSocket);
    $result = json_decode($resultJson, true, flags: JSON_THROW_ON_ERROR);
    fclose($parentSocket);
    pcntl_waitpid($pid, $childStatus);

    return [
        'ready' => $readySignal,
        'guard' => $secondSignalReady ? $guardSignal : '',
        'completed_while_locked' => $completedWhileLifecycleLockWasHeld,
        'exit' => pcntl_wexitstatus($childStatus),
        'response' => $result,
    ];
}

it('serializes Draft creation behind a committed lifecycle change', function () {
    [$student, $course] = lifecycleConcurrencyWorld();
    $race = raceHttpBehindCommittedTermination(
        $student,
        (int) $student->user_id,
        'POST',
        '/api/v1/credit-decisions/exemption-requests',
        [
            'target_course_id' => $course->id,
            'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
        ],
    );

    expect($race['ready'])->toBe('R')
        ->and($race['guard'])->toBe('L')
        ->and($race['completed_while_locked'])->toBeFalse()
        ->and($race['exit'])->toBe(0)
        ->and($race['response']['status'])->toBe(409)
        ->and($race['response']['body']['message'])->toBe(__('credit_decisions.student_inactive'))
        ->and(ExemptionRequest::query()->where('student_id', $student->id)->count())->toBe(0);
});

it('rolls back final approval when termination commits first', function () {
    [$student, $course, $head] = lifecycleConcurrencyWorld();
    $request = pendingHeadRaceRequest($student, $course);

    $race = raceHttpBehindCommittedTermination(
        $student,
        (int) $head->id,
        'POST',
        "/api/v1/credit-decisions/exemption-requests/{$request->id}/department-head-decision",
        [
            'action' => 'approve',
            'expected_revision' => 1,
            'action_id' => '693a28f9-090d-4a13-82d0-a71da1e157c2',
        ],
    );

    expect($race['ready'])->toBe('R')
        ->and($race['guard'])->toBe('L')
        ->and($race['completed_while_locked'])->toBeFalse()
        ->and($race['exit'])->toBe(0)
        ->and($race['response']['status'])->toBe(409)
        ->and(ExemptionDecision::query()->where('manual_request_id', $request->id)->count())->toBe(0)
        ->and($request->fresh()->status)->toBe(ExemptionRequestStatus::PendingDepartmentHead)
        ->and($request->transitions()->count())->toBe(1);
});

it('makes an Exemption-first finalizer observe the effective decision and reject stale termination', function () {
    [$student, $course, $head] = lifecycleConcurrencyWorld();
    $request = pendingHeadRaceRequest($student, $course);
    DB::disconnect();
    $approvalSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $terminationSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($approvalSockets === false || $terminationSockets === false) {
        throw new RuntimeException('Could not create the inverse lifecycle-race sockets.');
    }
    [$approvalParent, $approvalChild] = $approvalSockets;
    [$terminationParent, $terminationChild] = $terminationSockets;

    $approvalPid = pcntl_fork();
    if ($approvalPid === 0) {
        fclose($approvalParent);
        fclose($terminationParent);
        fclose($terminationChild);
        DB::disconnect();
        fread($approvalChild, 1);
        DB::reconnect();
        $guard = app(StudentGuard::class);
        app()->instance(StudentGuard::class, new class($guard, $approvalChild) implements StudentGuard
        {
            /** @param resource $signal */
            public function __construct(
                private readonly StudentGuard $guard,
                private $signal,
            ) {}

            public function lock(int $studentId): ?StudentSummary
            {
                $student = $this->guard->lock($studentId);
                fwrite($this->signal, 'L');
                fread($this->signal, 1);

                return $student;
            }
        });
        Sanctum::actingAs(User::query()->findOrFail($head->id));
        fwrite($approvalChild, 'R');
        $httpRequest = Request::create(
            "/api/v1/credit-decisions/exemption-requests/{$request->id}/department-head-decision",
            'POST',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            json_encode([
                'action' => 'approve',
                'expected_revision' => 1,
                'action_id' => 'e9afb44e-354f-424f-97fe-01b1776fbd11',
            ], JSON_THROW_ON_ERROR),
        );
        $kernel = app(Kernel::class);
        $response = $kernel->handle($httpRequest);
        $kernel->terminate($httpRequest, $response);
        fwrite($approvalChild, json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR));
        fclose($approvalChild);
        exit(0);
    }
    if ($approvalPid < 1) {
        throw new RuntimeException('Could not fork the approval process.');
    }

    $terminationPid = pcntl_fork();
    if ($terminationPid === 0) {
        fclose($terminationParent);
        fclose($approvalParent);
        fclose($approvalChild);
        DB::disconnect();
        fread($terminationChild, 1);
        DB::reconnect();
        fwrite($terminationChild, 'R');
        $terminationApplied = DB::transaction(function () use ($student): bool {
            $guarded = app(StudentGuard::class)->lock((int) $student->id);
            if ($guarded === null || app(ExemptionDecisions::class)->effectiveForStudent((int) $student->id) !== []) {
                return false;
            }
            app(StudentLifecycleWriter::class)->terminate((int) $student->id);

            return true;
        });
        fwrite($terminationChild, json_encode(['applied' => $terminationApplied], JSON_THROW_ON_ERROR));
        fclose($terminationChild);
        exit(0);
    }
    if ($terminationPid < 1) {
        throw new RuntimeException('Could not fork the termination process.');
    }

    fclose($approvalChild);
    fclose($terminationChild);
    fwrite($approvalParent, '1');
    $approvalReady = fread($approvalParent, 1);
    $approvalLocked = fread($approvalParent, 1);
    fwrite($terminationParent, '1');
    $terminationReady = fread($terminationParent, 1);
    $read = [$terminationParent];
    $write = null;
    $except = null;
    $terminationCompletedWhileApprovalHeldGuard = stream_select($read, $write, $except, 0, 250_000) === 1;
    fwrite($approvalParent, 'C');

    $approvalResult = json_decode(stream_get_contents($approvalParent), true, flags: JSON_THROW_ON_ERROR);
    $terminationResult = json_decode(stream_get_contents($terminationParent), true, flags: JSON_THROW_ON_ERROR);
    fclose($approvalParent);
    fclose($terminationParent);
    pcntl_waitpid($approvalPid, $approvalStatus);
    pcntl_waitpid($terminationPid, $terminationStatus);
    DB::reconnect();

    expect($approvalReady)->toBe('R')
        ->and($approvalLocked)->toBe('L')
        ->and($terminationReady)->toBe('R')
        ->and($terminationCompletedWhileApprovalHeldGuard)->toBeFalse()
        ->and($approvalResult['status'])->toBe(200)
        ->and($terminationResult['applied'])->toBeFalse()
        ->and(pcntl_wexitstatus($approvalStatus))->toBe(0)
        ->and(pcntl_wexitstatus($terminationStatus))->toBe(0)
        ->and(ExemptionDecision::query()->where('manual_request_id', $request->id)->count())->toBe(1)
        ->and($student->fresh()->lifecycle_status)->toBe(StudentLifecycleStatus::Active);
});

it('serializes automatic reconciliation ahead of a competing manual final approval', function () {
    [$student, $course, $head] = lifecycleConcurrencyWorld();
    $request = pendingHeadRaceRequest($student, $course);
    $program = Program::query()->findOrFail($student->program_id);
    AutomaticExemptionEvidence::query()->create([
        'student_id' => $student->id,
        'admission_application_id' => 99101,
        'evidence' => [
            'student_id' => $student->id,
            'user_id' => $student->user_id,
            'application_id' => 99101,
            'admitted_program_id' => $student->program_id,
            'admitted_program_degree_level' => $program->degree_level->value,
            'admitted_department_id' => $program->department_id,
            'previous_degree' => 'BSc',
            'is_ejust_graduate' => false,
            'previous_ejust_program_id' => null,
            'previous_ejust_program_degree_level' => null,
            'previous_ejust_department_id' => null,
            'english_test' => 'IELTS',
            'english_score' => 7.0,
        ],
        'captured_at' => now(),
    ]);

    DB::disconnect();
    $automaticSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    $manualSockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    if ($automaticSockets === false || $manualSockets === false) {
        throw new RuntimeException('Could not create the decision-race sockets.');
    }
    [$automaticParent, $automaticChild] = $automaticSockets;
    [$manualParent, $manualChild] = $manualSockets;

    $automaticPid = pcntl_fork();
    if ($automaticPid === 0) {
        fclose($automaticParent);
        fclose($manualParent);
        fclose($manualChild);
        DB::disconnect();
        fread($automaticChild, 1);
        DB::reconnect();
        $guard = app(StudentGuard::class);
        app()->instance(StudentGuard::class, new class($guard, $automaticChild) implements StudentGuard
        {
            /** @param resource $signal */
            public function __construct(
                private readonly StudentGuard $guard,
                private $signal,
            ) {}

            public function lock(int $studentId): ?StudentSummary
            {
                $student = $this->guard->lock($studentId);
                fwrite($this->signal, 'L');
                fread($this->signal, 1);

                return $student;
            }
        });
        fwrite($automaticChild, 'R');
        try {
            $created = app(AutomaticExemptionApplicator::class)->applyForStudent((int) $student->id);
            fwrite($automaticChild, json_encode(['created' => $created], JSON_THROW_ON_ERROR));
        } catch (Throwable $error) {
            fwrite($automaticChild, json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR));
        }
        fclose($automaticChild);
        exit(0);
    }
    if ($automaticPid < 1) {
        throw new RuntimeException('Could not fork the automatic decision process.');
    }

    $manualPid = pcntl_fork();
    if ($manualPid === 0) {
        fclose($manualParent);
        fclose($automaticParent);
        fclose($automaticChild);
        DB::disconnect();
        fread($manualChild, 1);
        DB::reconnect();
        $guard = app(StudentGuard::class);
        app()->instance(StudentGuard::class, new class($guard, $manualChild) implements StudentGuard
        {
            /** @param resource $signal */
            public function __construct(
                private readonly StudentGuard $guard,
                private $signal,
            ) {}

            public function lock(int $studentId): ?StudentSummary
            {
                fwrite($this->signal, 'G');

                return $this->guard->lock($studentId);
            }
        });
        Sanctum::actingAs(User::query()->findOrFail($head->id));
        fwrite($manualChild, 'R');
        $httpRequest = Request::create(
            "/api/v1/credit-decisions/exemption-requests/{$request->id}/department-head-decision",
            'POST',
            [],
            [],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            json_encode([
                'action' => 'approve',
                'expected_revision' => 1,
                'action_id' => '996794cf-8764-412e-a299-82afacf23a08',
            ], JSON_THROW_ON_ERROR),
        );
        $kernel = app(Kernel::class);
        $response = $kernel->handle($httpRequest);
        $kernel->terminate($httpRequest, $response);
        fwrite($manualChild, json_encode([
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR),
        ], JSON_THROW_ON_ERROR));
        fclose($manualChild);
        exit(0);
    }
    if ($manualPid < 1) {
        throw new RuntimeException('Could not fork the manual decision process.');
    }

    fclose($automaticChild);
    fclose($manualChild);
    fwrite($automaticParent, 'A');
    $automaticReady = fread($automaticParent, 1);
    $automaticGuardSignal = fread($automaticParent, 1);
    fwrite($manualParent, 'M');
    $manualReady = fread($manualParent, 1);
    $manualGuardSignal = fread($manualParent, 1);
    $read = [$manualParent];
    $write = null;
    $except = null;
    $manualCompletedWhileAutomaticHeldGuard = stream_select($read, $write, $except, 0, 250_000) === 1;
    if ($automaticGuardSignal === 'L') {
        fwrite($automaticParent, 'C');
    }

    $automaticJson = ($automaticGuardSignal === 'L' ? '' : $automaticGuardSignal).stream_get_contents($automaticParent);
    $automaticResult = json_decode($automaticJson, true, flags: JSON_THROW_ON_ERROR);
    $manualResult = json_decode(stream_get_contents($manualParent), true, flags: JSON_THROW_ON_ERROR);
    fclose($automaticParent);
    fclose($manualParent);
    pcntl_waitpid($automaticPid, $automaticStatus);
    pcntl_waitpid($manualPid, $manualStatus);
    DB::reconnect();

    expect($automaticReady)->toBe('R')
        ->and($automaticGuardSignal)->toBe('L')
        ->and($manualReady)->toBe('R')
        ->and($manualGuardSignal)->toBe('G')
        ->and($manualCompletedWhileAutomaticHeldGuard)->toBeFalse()
        ->and($automaticResult['created'])->toBe(1)
        ->and($manualResult['status'])->toBe(409)
        ->and($manualResult['body']['message'])->toBe(__('credit_decisions.effective_decision_exists'))
        ->and(pcntl_wexitstatus($automaticStatus))->toBe(0)
        ->and(pcntl_wexitstatus($manualStatus))->toBe(0)
        ->and(ExemptionDecision::query()->where('student_id', $student->id)->where('target_course_id', $course->id)->count())->toBe(1)
        ->and(ExemptionDecision::query()->where('manual_request_id', $request->id)->count())->toBe(0)
        ->and($request->fresh()->status)->toBe(ExemptionRequestStatus::PendingDepartmentHead);
});
