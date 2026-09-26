<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\CourseCategory;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Enrollment\Internal\Enums\EnrollmentClassification;
use Modules\Enrollment\Internal\Enums\ExemptionRequestStatus;
use Modules\Enrollment\Internal\Enums\WaiverRequestStatus;
use Modules\Enrollment\Internal\Models\CourseOffering;
use Modules\Enrollment\Internal\Models\Enrollment;
use Modules\Enrollment\Internal\Models\ExemptionDecision;
use Modules\Enrollment\Internal\Models\ExemptionRequest;
use Modules\Enrollment\Internal\Models\ExemptionRequestTransition;
use Modules\Enrollment\Internal\Models\WaiverDecision;
use Modules\Enrollment\Internal\Models\WaiverRequest;
use Modules\Enrollment\Public\ExemptionDecisions;
use Modules\Grading\Internal\Enums\GradeSheetStatus;
use Modules\Grading\Internal\Models\Grade;
use Modules\Grading\Internal\Models\GradeSheet;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Modules\Identity\Public\StudentSummary;
use Modules\Notifications\Internal\Models\Notification;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

/** @return array{Student, User, User, Course} */
function manualExemptionWorld(): array
{
    $department = Department::factory()->create();
    $program = Program::factory()->create(['department_id' => $department->id]);
    $course = Course::factory()->create([
        'department_id' => $department->id,
        'code' => 'PRE-ENG',
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

    return [$student, $advisor, $head, $course];
}

function manualRequestFor(Student $student, Course $course, ExemptionRequestStatus $status): ExemptionRequest
{
    return ExemptionRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage,
        'credit_hours' => $course->total_ch,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
        'status' => $status,
        'revision' => 1,
        'submitted_at' => $status === ExemptionRequestStatus::Draft ? null : now(),
    ]);
}

it('blocks a manual Exemption target already recognized by Waiver', function () {
    [$student, , $head, $course] = manualExemptionWorld();
    $prior = Course::factory()->create(['department_id' => $course->department_id, 'code' => 'PRE-OLD']);
    $waiverRequest = WaiverRequest::query()->create([
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'credit_hours' => $course->total_ch,
        'prior_course_id' => $prior->id,
        'prior_course_code' => $prior->code,
        'prior_course_level' => $prior->level->value,
        'prior_result_source' => 'legacy_transcript',
        'prior_letter_grade' => 'A',
        'evidence' => ['official_transcript_document_ids' => ['01ARZ3NDEKTSV4RRFFQ69G5FAW']],
        'status' => WaiverRequestStatus::Approved,
        'revision' => 1,
        'submitted_at' => now(),
        'coverage_percentage' => 90,
        'equivalency_note' => 'Equivalent.',
        'verified_by' => $head->id,
        'verified_at' => now(),
    ]);
    WaiverDecision::query()->create([
        'waiver_request_id' => $waiverRequest->id,
        'student_id' => $student->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $course->code,
        'credit_hours' => $course->total_ch,
        'prior_course_id' => $prior->id,
        'prior_course_code' => $prior->code,
        'prior_course_level' => $prior->level->value,
        'prior_result_source' => 'legacy_transcript',
        'prior_letter_grade' => 'A',
        'coverage_percentage' => 90,
        'verified_by' => $head->id,
        'verified_at' => now(),
        'approved_by' => $head->id,
        'approved_at' => now(),
    ]);
    $request = manualRequestFor($student, $course, ExemptionRequestStatus::PendingDepartmentHead);

    Sanctum::actingAs($student->user);
    $this->getJson('/api/v1/credit-decisions/exemption-requests/targets')
        ->assertOk()->assertJsonPath('data.0.already_recognized', true);
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$request->id}/department-head-decision", [
        'action' => 'approve',
        'expected_revision' => 1,
        'action_id' => '20202020-2020-4020-8020-202020202020',
    ])->assertConflict();
});

it('applies the shared actor-aware collection and export contract', function (string $surface) {
    [$student, $advisor, $head, $course] = manualExemptionWorld();
    $status = match ($surface) {
        'student' => ExemptionRequestStatus::Draft,
        'advisor' => ExemptionRequestStatus::PendingAdvisor,
        'department_head' => ExemptionRequestStatus::PendingDepartmentHead,
    };
    manualRequestFor($student, $course, $status);
    manualRequestFor($student, $course, $status);

    $outsideDepartment = Department::factory()->create();
    $outsideProgram = Program::factory()->create(['department_id' => $outsideDepartment->id]);
    $outsideCourse = Course::factory()->create([
        'department_id' => $outsideDepartment->id,
        'code' => 'PRE-OUT',
        'lecture_ch' => 1,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 1,
    ]);
    $outsideAdvisor = User::factory()->create();
    $outsideAdvisor->assignRole(Role::Advisor);
    $outsideStudent = Student::factory()->create([
        'program_id' => $outsideProgram->id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'advisor_id' => $outsideAdvisor->id,
    ]);
    manualRequestFor($outsideStudent, $outsideCourse, $status);

    [$actor, $endpoint] = match ($surface) {
        'student' => [$student->user, '/api/v1/credit-decisions/exemption-requests'],
        'advisor' => [$advisor, '/api/v1/credit-decisions/exemption-requests/advisor-queue'],
        'department_head' => [$head, '/api/v1/credit-decisions/exemption-requests/department-head-queue'],
    };
    Sanctum::actingAs($actor);

    $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?unknown_field=1')
        ->assertUnprocessable()
        ->assertJsonPath('message', 'حقل استعلام المجموعة غير معروف: unknown_field.');
    $invalidTargetMessage = 'يجب أن يحتوي حقل استعلام المجموعة target_course_id على أعداد صحيحة موجبة فقط.';
    $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?target_course_id=abc')
        ->assertUnprocessable()
        ->assertJsonPath('message', $invalidTargetMessage);
    $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'/export?target_course_id=abc')
        ->assertUnprocessable()
        ->assertJsonPath('message', $invalidTargetMessage);
    $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?target_course_id[]=abc')
        ->assertUnprocessable()
        ->assertJsonPath('message', $invalidTargetMessage);
    $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'/export?target_course_id[]=abc')
        ->assertUnprocessable()
        ->assertJsonPath('message', $invalidTargetMessage);
    if ($surface !== 'student') {
        $invalidStudentMessage = 'يجب أن يحتوي حقل استعلام المجموعة student_id على أعداد صحيحة موجبة فقط.';
        $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?student_id=10,nope')
            ->assertUnprocessable()
            ->assertJsonPath('message', $invalidStudentMessage);
        $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'/export?student_id=10,nope')
            ->assertUnprocessable()
            ->assertJsonPath('message', $invalidStudentMessage);
        $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'?student_id[]=abc')
            ->assertUnprocessable()
            ->assertJsonPath('message', $invalidStudentMessage);
        $this->withHeader('Accept-Language', 'ar')->getJson($endpoint.'/export?student_id[]=abc')
            ->assertUnprocessable()
            ->assertJsonPath('message', $invalidStudentMessage);
    }
    $this->getJson($endpoint.'?target_course_id='.$course->id.','.$outsideCourse->id)
        ->assertOk()->assertJsonPath('meta.total', 2);
    $this->getJson($endpoint.'?target_course_id='.$outsideCourse->id)
        ->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson($endpoint.'?national_id=29001011234567')
        ->assertOk()->assertJsonPath('meta.total', 2)
        ->assertJsonMissingPath('data.0.national_id')
        ->assertJsonMissingPath('data.0.student.national_id');
    $page = $this->getJson($endpoint.'?per_page=1')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.last_page', 2);
    expect($page->json('data'))->toHaveCount(1);

    $this->get($endpoint.'/export?target_course_id='.$outsideCourse->id)
        ->assertOk()->assertDownload();
    expect(AuditLog::query()->where('action', 'exemption.requests.exported')->latest('id')->firstOrFail()->metadata['row_count'])
        ->toBe(0);
})->with('manual exemption collections');

it('streams a search-filtered reviewer export through bounded Identity batches', function () {
    [$student, $advisor, , $course] = manualExemptionWorld();
    $otherStudents = Student::factory()->count(500)->create([
        'program_id' => $student->program_id,
        'curriculum_version_id' => $student->curriculum_version_id,
        'advisor_id' => $advisor->id,
    ]);
    $students = collect([$student])->concat($otherStudents)->values();
    $now = now();

    foreach (array_chunk($students->map(fn (Student $candidate, int $index): array => [
        'student_id' => $candidate->id,
        'curriculum_version_id' => $candidate->curriculum_version_id,
        'target_course_id' => $course->id,
        'target_course_code' => $index === 500 ? 'MATCH-LAST' : 'NO-MATCH',
        'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage->value,
        'credit_hours' => 1,
        'evidence' => json_encode(['english_test' => 'IELTS', 'english_score' => 7.0], JSON_THROW_ON_ERROR),
        'student_note' => null,
        'status' => ExemptionRequestStatus::PendingAdvisor->value,
        'revision' => 1,
        'submitted_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ])->all(), 250) as $requestRows) {
        DB::table('exemption_requests')->insert($requestRows);
    }

    $realDirectory = app(StudentDirectory::class);
    $batchProbe = new class($realDirectory) implements StudentDirectory
    {
        /** @var list<int> */
        public array $batchSizes = [];

        public function __construct(private readonly StudentDirectory $inner) {}

        public function find(int $userId): ?StudentSummary
        {
            return $this->inner->find($userId);
        }

        public function findByUniversityId(string $universityId): ?StudentSummary
        {
            return $this->inner->findByUniversityId($universityId);
        }

        public function findByStudentId(int $studentId): ?StudentSummary
        {
            return $this->inner->findByStudentId($studentId);
        }

        /** @param list<int> $studentIds */
        public function findManyByStudentId(array $studentIds): array
        {
            $this->batchSizes[] = count($studentIds);
            throw_if(count($studentIds) > 500, LogicException::class, 'Identity batch exceeded export bound.');

            return $this->inner->findManyByStudentId($studentIds);
        }

        public function studentIdsAdvisedBy(int $advisorUserId): array
        {
            return $this->inner->studentIdsAdvisedBy($advisorUserId);
        }

        public function allStudentIds(): array
        {
            return $this->inner->allStudentIds();
        }
    };
    app()->instance(StudentDirectory::class, $batchProbe);
    Sanctum::actingAs($advisor);

    $this->get('/api/v1/credit-decisions/exemption-requests/advisor-queue/export?q=MATCH-LAST')
        ->assertOk()
        ->assertDownload('advisor-exemption-queue.xlsx');

    expect($batchProbe->batchSizes)->not->toBeEmpty()
        ->and(max($batchProbe->batchSizes))->toBeLessThanOrEqual(500)
        ->and(AuditLog::query()->where('action', 'exemption.requests.exported')->latest('id')->firstOrFail()->metadata['row_count'])
        ->toBe(1);
});

it('hydrates only the latest transition when streaming Student and reviewer exports', function (string $surface) {
    [$student, $advisor, , $course] = manualExemptionWorld();
    $status = $surface === 'student'
        ? ExemptionRequestStatus::Draft
        : ExemptionRequestStatus::PendingAdvisor;
    $request = manualRequestFor($student, $course, $status);
    $now = now();
    $transitionCount = 201;
    $transitionRows = collect(range(1, $transitionCount))->map(fn (int $index): array => [
        'exemption_request_id' => $request->id,
        'from_status' => null,
        'to_status' => $status->value,
        'action' => 'history_'.$index,
        'actor_user_id' => $student->user_id,
        'revision' => 1,
        'evidence_snapshot' => json_encode($request->evidence, JSON_THROW_ON_ERROR),
        'action_id' => null,
        'reason' => $index === $transitionCount ? 'LATEST-EXPORT-REASON' : 'superseded reason',
        'created_at' => $now->copy()->addSeconds($index),
    ])->all();
    foreach (array_chunk($transitionRows, 100) as $chunk) {
        DB::table('exemption_request_transitions')->insert($chunk);
    }

    [$actor, $endpoint] = $surface === 'student'
        ? [$student->user, '/api/v1/credit-decisions/exemption-requests']
        : [$advisor, '/api/v1/credit-decisions/exemption-requests/advisor-queue'];
    Sanctum::actingAs($actor);
    $this->getJson($endpoint)
        ->assertOk()
        ->assertJsonCount($transitionCount, 'data.0.transitions')
        ->assertJsonPath('data.0.transitions.'.($transitionCount - 1).'.reason', 'LATEST-EXPORT-REASON');

    $retrievedTransitions = 0;
    Event::listen(
        'eloquent.retrieved: '.ExemptionRequestTransition::class,
        function () use (&$retrievedTransitions): void {
            $retrievedTransitions++;
        },
    );

    $response = $this->get($endpoint.'/export')->assertOk()->assertDownload();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $exportedRows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $exportedRows[] = $row->toArray();
        }
    }
    $reader->close();

    expect($retrievedTransitions)->toBe(1)
        ->and($exportedRows)->toHaveCount(2)
        ->and($exportedRows[1])->toContain('LATEST-EXPORT-REASON');
})->with([
    'Student export' => 'student',
    'reviewer export' => 'advisor',
]);

it('takes corrected evidence from Draft through scoped review to an immutable decision', function () {
    [$student, $advisor, $head, $course] = manualExemptionWorld();

    Sanctum::actingAs($student->user);
    $created = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
        'student_note' => 'Corrected certificate attached.',
    ])->assertCreated()->assertJsonPath('data.status', 'Draft');
    $id = $created->json('data.id');
    $revision = $created->json('data.revision');

    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => $revision,
        'action_id' => '98e597dd-8267-4bc1-934e-91f8324e0ec1',
    ])->assertOk()->assertJsonPath('data.status', 'PendingAdvisor');

    Sanctum::actingAs($advisor);
    $student->user->update(['name' => 'أَحْمَد']);
    $this->getJson('/api/v1/credit-decisions/exemption-requests/advisor-queue?q=احمد&per_page=1')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id);
    $this->getJson('/api/v1/credit-decisions/exemption-requests/advisor-queue?unknown=1')
        ->assertUnprocessable();
    $this->getJson('/api/v1/credit-decisions/exemption-requests/advisor-queue?target_course_id=999999')
        ->assertOk()->assertJsonPath('meta.total', 0);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend',
        'expected_revision' => $revision,
        'action_id' => 'c63e0602-5eb5-44a9-a8dd-056da2ae03ea',
    ])->assertOk()->assertJsonPath('data.status', 'PendingDepartmentHead');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend',
        'expected_revision' => $revision,
        'action_id' => 'c63e0602-5eb5-44a9-a8dd-056da2ae03ea',
    ])->assertOk()->assertJsonPath('data.status', 'PendingDepartmentHead');

    Sanctum::actingAs($head);
    $this->get('/api/v1/credit-decisions/exemption-requests/department-head-queue/export', ['Accept-Language' => 'ar'])
        ->assertOk()
        ->assertDownload('department-head-exemption-queue.xlsx');
    $this->assertDatabaseHas('audit_log', ['action' => 'exemption.requests.exported']);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'approve',
        'expected_revision' => $revision,
        'action_id' => 'c482238d-e722-4a61-9c97-aaf4aa7a441f',
    ])->assertOk()->assertJsonPath('data.status', 'Approved')->assertJsonPath('data.decision.source', 'manual');

    $studentNotification = Notification::query()->where('user_id', $student->user_id)->where('type', 'exemption.request_decided')->sole();
    $advisorNotification = Notification::query()->where('user_id', $advisor->id)->where('type', 'exemption.request_decided')->sole();
    expect($studentNotification->data)->not->toHaveKey('audience')
        ->and($advisorNotification->data['audience'] ?? null)->toBe('advisor');

    $this->assertDatabaseHas('exemption_decisions', [
        'student_id' => $student->id,
        'target_course_id' => $course->id,
        'manual_request_id' => $id,
        'source' => 'manual',
    ]);
    $this->assertDatabaseCount('exemption_request_transitions', 4);
    $this->assertDatabaseHas('audit_log', ['action' => 'exemption.request.approved']);
    expect(app(ExemptionDecisions::class)->effectiveForStudent((int) $student->id))
        ->toHaveCount(1)
        ->and(app(ExemptionDecisions::class)->effectiveForStudent((int) $student->id)[0]->source)
        ->toBe('manual');
    Sanctum::actingAs($student->user);
    $this->getJson('/api/v1/credit-decisions/exemptions')
        ->assertOk()
        ->assertJsonPath('data.0.source', 'manual')
        ->assertJsonPath('data.0.admission_application_id', null)
        ->assertJsonPath('data.0.manual_request_id', $id);
    $decision = ExemptionDecision::query()->where('manual_request_id', $id)->firstOrFail();
    expect(fn () => $decision->update(['credit_hours' => 99]))->toThrow(LogicException::class);
});

it('keeps request transitions append-only and requires abandonment instead of hard deletion', function () {
    [$student, , , $course] = manualExemptionWorld();
    Sanctum::actingAs($student->user);

    $id = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->assertCreated()->json('data.id');
    $request = ExemptionRequest::query()->findOrFail($id);
    $transition = $request->transitions()->firstOrFail();

    expect(fn () => $transition->update(['reason' => 'rewritten']))
        ->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $transition->delete())
        ->toThrow(LogicException::class, 'append-only')
        ->and(fn () => $request->delete())
        ->toThrow(LogicException::class, 'cannot be hard-deleted');

    $this->getJson("/api/v1/credit-decisions/exemption-requests/{$id}")
        ->assertOk()
        ->assertJsonPath('data.transitions.0.action', 'draft_created');

    expect(fn () => DB::table('exemption_requests')->where('id', $id)->delete())
        ->toThrow(QueryException::class);
});

it('revalidates the Student lifecycle under the Identity guard before final approval', function () {
    [$student, $advisor, $head, $course] = manualExemptionWorld();

    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => 'f8befb19-1b68-4504-8747-b9eaa0d7d041',
    ])->assertOk();

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend',
        'expected_revision' => 1,
        'action_id' => 'fb41d7c2-5e3c-4b69-9476-8ad1c0281175',
    ])->assertOk();

    $student->forceFill(['lifecycle_status' => 'Terminated'])->save();
    Sanctum::actingAs($student->user);
    $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->assertConflict()->assertJsonPath('message', __('credit_decisions.student_inactive'));

    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'approve',
        'expected_revision' => 1,
        'action_id' => '9634bcca-9fae-4ff7-9767-2e2e35277a7a',
    ])->assertConflict()
        ->assertJsonPath('message', __('credit_decisions.student_inactive'));

    $this->assertDatabaseMissing('exemption_decisions', ['manual_request_id' => $id]);
    $this->assertDatabaseHas('exemption_requests', ['id' => $id, 'status' => 'PendingDepartmentHead']);
});

it('blocks every request mutation after the Student is Terminated', function () {
    config(['app.debug' => false]);
    [$student, $advisor, $head, $course] = manualExemptionWorld();
    $draft = manualRequestFor($student, $course, ExemptionRequestStatus::Draft);
    $advisorRequest = manualRequestFor($student, $course, ExemptionRequestStatus::PendingAdvisor);
    $headRequest = manualRequestFor($student, $course, ExemptionRequestStatus::PendingDepartmentHead);
    $outsideAdvisor = User::factory()->create();
    $outsideAdvisor->assignRole(Role::Advisor);
    $outsideHead = User::factory()->create();
    $outsideHead->assignRole(Role::DepartmentHead);

    Sanctum::actingAs($outsideAdvisor);
    $activeAdvisorResponse = $this->postJson("/api/v1/credit-decisions/exemption-requests/{$advisorRequest->id}/advisor-decision", [
        'action' => 'reject',
        'reason' => 'Outside scope',
        'expected_revision' => 1,
        'action_id' => '94f681bf-5458-4034-93dd-37a7ae8b9293',
    ])->assertForbidden();
    Sanctum::actingAs($outsideHead);
    $activeHeadResponse = $this->postJson("/api/v1/credit-decisions/exemption-requests/{$headRequest->id}/department-head-decision", [
        'action' => 'reject',
        'reason' => 'Outside scope',
        'expected_revision' => 1,
        'action_id' => '4bf35024-0156-442a-9513-d98777430448',
    ])->assertForbidden();

    $student->forceFill(['lifecycle_status' => 'Terminated'])->save();

    Sanctum::actingAs($student->user);
    $this->patchJson("/api/v1/credit-decisions/exemption-requests/{$draft->id}", [
        'expected_revision' => 1,
        'student_note' => 'Must not change',
    ])->assertConflict();
    $this->deleteJson("/api/v1/credit-decisions/exemption-requests/{$draft->id}", [
        'expected_revision' => 1,
    ])->assertConflict();
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$draft->id}/submit", [
        'expected_revision' => 1,
        'action_id' => '62cd0049-08ab-40a2-bf89-e536ef42f6d7',
    ])->assertConflict();

    Sanctum::actingAs($outsideAdvisor);
    $terminatedAdvisorResponse = $this->postJson("/api/v1/credit-decisions/exemption-requests/{$advisorRequest->id}/advisor-decision", [
        'action' => 'reject',
        'reason' => 'Outside scope',
        'expected_revision' => 1,
        'action_id' => '94f681bf-5458-4034-93dd-37a7ae8b9293',
    ])->assertForbidden();

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$advisorRequest->id}/advisor-decision", [
        'action' => 'reject',
        'reason' => 'Must not change',
        'expected_revision' => 1,
        'action_id' => '86624f6b-8ac5-405f-b390-2dafc25ca1b8',
    ])->assertConflict();

    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$headRequest->id}/department-head-decision", [
        'action' => 'reject',
        'reason' => 'Must not change',
        'expected_revision' => 1,
        'action_id' => 'fb3bd9bb-e090-414e-a7ed-8630253540b1',
    ])->assertConflict();

    Sanctum::actingAs($outsideHead);
    $terminatedHeadResponse = $this->postJson("/api/v1/credit-decisions/exemption-requests/{$headRequest->id}/department-head-decision", [
        'action' => 'reject',
        'reason' => 'Outside scope',
        'expected_revision' => 1,
        'action_id' => '4bf35024-0156-442a-9513-d98777430448',
    ])->assertForbidden();

    expect($terminatedAdvisorResponse->json())->toBe($activeAdvisorResponse->json())
        ->and($terminatedHeadResponse->json())->toBe($activeHeadResponse->json())
        ->and([$draft->fresh()->status, $advisorRequest->fresh()->status, $headRequest->fresh()->status])
        ->toBe([
            ExemptionRequestStatus::Draft,
            ExemptionRequestStatus::PendingAdvisor,
            ExemptionRequestStatus::PendingDepartmentHead,
        ]);
});

it('gives an out-of-scope Department Head the same response for existing and unavailable targets across lifecycle states', function () {
    config(['app.debug' => false]);
    [$student, , , $course] = manualExemptionWorld();
    $existingTargetRequest = manualRequestFor($student, $course, ExemptionRequestStatus::PendingDepartmentHead);
    $missingCourse = Course::factory()->create(['department_id' => $course->department_id]);
    $missingTargetRequest = manualRequestFor($student, $missingCourse, ExemptionRequestStatus::PendingDepartmentHead);
    $outsideHead = User::factory()->create();
    $outsideHead->assignRole(Role::DepartmentHead);
    $missingCourse->delete();

    Sanctum::actingAs($outsideHead);
    $action = [
        'action' => 'reject',
        'reason' => 'Outside scope',
        'expected_revision' => 1,
        'action_id' => '24dc8929-5694-49e8-857c-e4a4cd70dcfa',
    ];
    $existingActiveResponse = $this->postJson(
        "/api/v1/credit-decisions/exemption-requests/{$existingTargetRequest->id}/department-head-decision",
        $action,
    )->assertForbidden();
    $missingActiveResponse = $this->postJson(
        "/api/v1/credit-decisions/exemption-requests/{$missingTargetRequest->id}/department-head-decision",
        $action,
    )->assertForbidden();

    $student->forceFill(['lifecycle_status' => 'Terminated'])->save();
    $existingTerminatedResponse = $this->postJson(
        "/api/v1/credit-decisions/exemption-requests/{$existingTargetRequest->id}/department-head-decision",
        $action,
    )->assertForbidden();
    $missingTerminatedResponse = $this->postJson(
        "/api/v1/credit-decisions/exemption-requests/{$missingTargetRequest->id}/department-head-decision",
        $action,
    )->assertForbidden();

    expect($missingActiveResponse->json())->toBe($existingActiveResponse->json())
        ->and($existingTerminatedResponse->json())->toBe($existingActiveResponse->json())
        ->and($missingTerminatedResponse->json())->toBe($existingActiveResponse->json())
        ->and($existingTargetRequest->fresh()->status)->toBe(ExemptionRequestStatus::PendingDepartmentHead)
        ->and($missingTargetRequest->fresh()->status)->toBe(ExemptionRequestStatus::PendingDepartmentHead)
        ->and($existingTargetRequest->transitions()->count())->toBe(0)
        ->and($missingTargetRequest->transitions()->count())->toBe(0);
});

it('keeps target-unavailable as a domain error after Advisor authorization and rolls back the recommendation', function () {
    [$student, $advisor, , $course] = manualExemptionWorld();
    $request = manualRequestFor($student, $course, ExemptionRequestStatus::PendingAdvisor);
    $course->delete();

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$request->id}/advisor-decision", [
        'action' => 'recommend',
        'expected_revision' => 1,
        'action_id' => 'b2cdf303-af72-4590-bdb6-142f33c41024',
    ])->assertConflict()->assertJsonPath('message', __('credit_decisions.target_unavailable'));

    expect($request->fresh()->status)->toBe(ExemptionRequestStatus::PendingAdvisor)
        ->and($request->transitions()->count())->toBe(0);
});

it('enforces the selected English test score range on every evidence write', function (string $test, float $score) {
    [$student, , , $course] = manualExemptionWorld();
    Sanctum::actingAs($student->user);

    $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => $test, 'english_score' => $score],
    ])->assertUnprocessable()->assertJsonValidationErrors(['evidence.english_score']);
})->with([
    'IELTS above 9' => ['IELTS', 9.5],
    'TOEFL iBT above 120' => ['TOEFL iBT', 121],
]);

it('returns a request for editable revision and invalidates the earlier recommendation', function () {
    [$student, $advisor, $head, $course] = manualExemptionWorld();
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 6.0],
    ])->json('data.id');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => '37af23e8-345b-4dc3-8917-ff871aaf4905',
    ])->assertOk();

    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'return',
        'reason' => 'Provide a valid score report.',
        'expected_revision' => 1,
        'action_id' => '8ab41a38-09c0-47aa-9f3d-ed778e31b157',
    ])->assertOk()->assertJsonPath('data.status', 'Returned');

    Sanctum::actingAs($student->user);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => '6820570e-a6c7-4320-a6f8-21f12822ab87',
    ])->assertConflict();
    $this->deleteJson("/api/v1/credit-decisions/exemption-requests/{$id}", [
        'expected_revision' => 1,
    ])->assertConflict();
    $this->patchJson("/api/v1/credit-decisions/exemption-requests/{$id}", [
        'evidence' => ['english_test' => 'GRE', 'english_score' => 'not-a-score'],
        'expected_revision' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['evidence.english_test', 'evidence.english_score']);
    $this->patchJson("/api/v1/credit-decisions/exemption-requests/{$id}", [
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
        'expected_revision' => 1,
    ])->assertOk()->assertJsonPath('data.revision', 2);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 2,
        'action_id' => '5724422e-184d-4f11-af3c-abac68220963',
    ])->assertOk()->assertJsonPath('data.status', 'PendingAdvisor');

    $this->assertDatabaseHas('exemption_request_transitions', [
        'exemption_request_id' => $id,
        'action' => 'returned_by_advisor',
        'revision' => 1,
    ]);
    expect(ExemptionRequestTransition::query()->where('action', 'returned_by_advisor')->firstOrFail()->evidence_snapshot['english_score'])
        ->toBe(6);
    $this->getJson("/api/v1/credit-decisions/exemption-requests/{$id}")
        ->assertOk()->assertJsonPath('data.transitions.2.current_revision', false);
});

it('enforces live Advisor and Department Head scope and rejects stale actions', function () {
    [$student, $advisor, $head, $course] = manualExemptionWorld();
    $otherAdvisor = User::factory()->create();
    $otherAdvisor->assignRole(Role::Advisor);
    $otherHead = User::factory()->create();
    $otherHead->assignRole(Role::DepartmentHead);

    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->json('data.id');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => '8fb8aa76-1665-49d0-a8c4-fd132022b082',
    ]);

    Sanctum::actingAs($otherAdvisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => 'e0ea30f0-3be9-4cf2-b698-f87262f4e719',
    ])->assertForbidden();

    $student->update(['advisor_id' => $otherAdvisor->id]);
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '6c52b842-ea50-4e57-a6a0-fc603ea33ca6',
    ])->assertForbidden();
    Sanctum::actingAs($otherAdvisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => 'e0ea30f0-3be9-4cf2-b698-f87262f4e719',
    ])->assertOk();

    Sanctum::actingAs($otherHead);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'approve', 'expected_revision' => 1,
        'action_id' => '02ce538c-1718-4b65-800c-fc889b8bf183',
    ])->assertForbidden();
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'approve', 'expected_revision' => 99,
        'action_id' => '0cbd5400-29c8-4bda-ab07-ed1a4838e464',
    ])->assertConflict();
});

it('revalidates live reviewer scope before returning an idempotent action replay', function () {
    [$student, $advisor, $head, $course] = manualExemptionWorld();
    $replacementAdvisor = User::factory()->create();
    $replacementAdvisor->assignRole(Role::Advisor);

    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 1,
        'action_id' => '34c25091-e610-4569-91b7-7ffdd05a3190',
    ])->assertOk();

    $advisorAction = [
        'action' => 'recommend',
        'expected_revision' => 1,
        'action_id' => 'dce93462-66a2-4a2b-a950-ff464a73cdf3',
    ];
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", $advisorAction)
        ->assertOk()->assertJsonPath('data.status', 'PendingDepartmentHead');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", $advisorAction)
        ->assertOk()->assertJsonPath('data.status', 'PendingDepartmentHead');

    $student->update(['advisor_id' => $replacementAdvisor->id]);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", $advisorAction)
        ->assertForbidden();

    $headAction = [
        'action' => 'approve',
        'expected_revision' => 1,
        'action_id' => '140ed830-3ec2-4df5-b07f-67db7b8afae7',
    ];
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", $headAction)
        ->assertOk()->assertJsonPath('data.status', 'Approved');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", $headAction)
        ->assertOk()->assertJsonPath('data.status', 'Approved');

    DepartmentHeadAssignment::query()->where('user_id', $head->id)->delete();
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", $headAction)
        ->assertForbidden();
});

it('approves corrected evidence after an Attempt without deleting study history', function (float $mark, string $letterGrade) {
    [$student, $advisor, $head, $course] = manualExemptionWorld();
    $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
    $enrollment = Enrollment::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'classification' => EnrollmentClassification::Preparatory,
        'credit_hours' => 1,
    ]);
    $sheet = GradeSheet::factory()->inStatus(GradeSheetStatus::Finalized)->create(['course_offering_id' => $offering->id]);
    $grade = Grade::factory()->withMark($mark)->create(['grade_sheet_id' => $sheet->id, 'enrollment_id' => $enrollment->id]);

    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.5],
    ])->json('data.id');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '0eaaf710-b35e-4c9f-8ad8-820dbd8c3a52',
    ])->assertOk();
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '2c733bd4-90a6-4cdd-8f77-14c7671370dd',
    ])->assertOk();
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'approve', 'expected_revision' => 1,
        'action_id' => '659b1263-c2fb-4fb1-b4df-b01339dc310d',
    ])->assertOk();

    $this->assertDatabaseHas('enrollments', ['id' => $enrollment->id, 'student_id' => $student->id]);
    $this->assertDatabaseHas('grades', ['id' => $grade->id, 'enrollment_id' => $enrollment->id, 'letter_grade' => $letterGrade]);
    $this->assertDatabaseHas('exemption_decisions', ['manual_request_id' => $id]);
})->with([
    'failed Attempt' => [45.0, 'F'],
    'successful Attempt' => [85.0, 'B+'],
]);

it('restricts abandonment to the owner and keeps abandoned and Advisor-rejected requests terminal', function () {
    [$student, $advisor, , $course] = manualExemptionWorld();

    Sanctum::actingAs($student->user);
    $abandonedId = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->assertCreated()->json('data.id');
    $otherStudent = Student::factory()->create();
    $otherStudent->user->assignRole(Role::Student);
    Sanctum::actingAs($otherStudent->user);
    $this->deleteJson("/api/v1/credit-decisions/exemption-requests/{$abandonedId}", [
        'expected_revision' => 1,
    ])->assertForbidden();
    Sanctum::actingAs($student->user);
    $this->deleteJson("/api/v1/credit-decisions/exemption-requests/{$abandonedId}", [
        'expected_revision' => 1,
    ])->assertOk()->assertJsonPath('data.status', 'Abandoned');
    $this->patchJson("/api/v1/credit-decisions/exemption-requests/{$abandonedId}", [
        'expected_revision' => 1,
        'student_note' => 'Too late',
    ])->assertConflict();

    $rejectedId = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$rejectedId}/submit", [
        'expected_revision' => 1,
        'action_id' => 'a8022298-f25a-4278-a379-005c5a6d5b23',
    ])->assertOk();
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$rejectedId}/advisor-decision", [
        'action' => 'reject',
        'reason' => 'The report cannot be verified.',
        'expected_revision' => 1,
        'action_id' => '95d5fbb9-2d69-4f61-b5aa-f7938ace77ad',
    ])->assertOk()->assertJsonPath('data.status', 'Rejected');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$rejectedId}/advisor-decision", [
        'action' => 'reject',
        'reason' => 'The report cannot be verified.',
        'expected_revision' => 1,
        'action_id' => '95d5fbb9-2d69-4f61-b5aa-f7938ace77ad',
    ])->assertOk()->assertJsonPath('data.status', 'Rejected');
});

it('supports Department Head return and terminal rejection after fresh Advisor review', function () {
    [$student, $advisor, $head, $course] = manualExemptionWorld();
    Sanctum::actingAs($student->user);
    $id = $this->postJson('/api/v1/credit-decisions/exemption-requests', [
        'target_course_id' => $course->id,
        'evidence' => ['english_test' => 'IELTS', 'english_score' => 7.0],
    ])->assertCreated()->json('data.id');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 1, 'action_id' => '27f47ce5-4396-46a7-9428-7aab6c345b05',
    ])->assertOk();
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 1,
        'action_id' => '53f6d52a-1a7e-47e2-9e5f-f6491618204a',
    ])->assertOk();
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'return', 'reason' => 'Clarify the score source.', 'expected_revision' => 1,
        'action_id' => 'b617261f-4afc-4741-a730-ab2a06ba9c28',
    ])->assertOk()->assertJsonPath('data.status', 'Returned');

    $studentNotification = Notification::query()->where('user_id', $student->user_id)->where('type', 'exemption.request_returned')->sole();
    $advisorNotification = Notification::query()->where('user_id', $advisor->id)->where('type', 'exemption.request_returned')->sole();
    expect($studentNotification->data)->not->toHaveKey('audience')
        ->and($advisorNotification->data['audience'] ?? null)->toBe('advisor');

    Sanctum::actingAs($student->user);
    $this->patchJson("/api/v1/credit-decisions/exemption-requests/{$id}", [
        'student_note' => 'Score source clarified.', 'expected_revision' => 1,
    ])->assertOk()->assertJsonPath('data.revision', 2);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/submit", [
        'expected_revision' => 2, 'action_id' => '2d261c2c-a413-49fd-89ac-304db7ec8321',
    ])->assertOk();
    Sanctum::actingAs($advisor);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/advisor-decision", [
        'action' => 'recommend', 'expected_revision' => 2,
        'action_id' => '915a7e15-b16c-4410-83d7-e649f8063e29',
    ])->assertOk();
    Sanctum::actingAs($head);
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'reject', 'reason' => 'Evidence remains unverifiable.', 'expected_revision' => 2,
        'action_id' => '6a7fe8fb-cf18-4365-af03-33f810be3f14',
    ])->assertOk()->assertJsonPath('data.status', 'Rejected');
    $this->postJson("/api/v1/credit-decisions/exemption-requests/{$id}/department-head-decision", [
        'action' => 'approve', 'expected_revision' => 2,
        'action_id' => '70f88d3c-9785-4a2b-a428-633833df17f1',
    ])->assertConflict();
});
