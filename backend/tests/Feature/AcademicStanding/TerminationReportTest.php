<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Internal\Models\StandingObservation;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Modules\Notifications\Internal\Models\Notification;

uses(RefreshDatabase::class);

it('distinguishes default Case counts from Student counts and retains closed replacements only in explicit history', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::StudentAffairs);
    $program = Program::factory()->create();
    $active = Student::factory()->create(['program_id' => $program->id]);
    $otherActive = Student::factory()->count(2)->create(['program_id' => $program->id]);
    $openStudents = [$active->id, ...$otherActive->modelKeys()];
    $terminated = Student::factory()->create(['program_id' => $program->id, 'lifecycle_status' => 'Terminated']);
    foreach (['AwaitingAdvisor', 'AwaitingDepartmentHead', 'AwaitingCouncil', 'Dismissed', 'TriggerCleared', 'EvidenceChanged', 'Terminated'] as $index => $state) {
        TerminationCase::query()->create([
            'student_id' => $state === 'Terminated' ? $terminated->id : ($openStudents[$index] ?? $active->id),
            'fingerprint' => str_pad((string) $index, 64, 'a'), 'state' => $state,
            'evidence' => ['private' => 'DO-NOT-DISCLOSE'],
            'advisor_assessment' => 'PRIVATE-ASSESSMENT', 'department_head_reason' => 'PRIVATE-RECOMMENDATION',
            'council_reason' => 'PRIVATE-DECISION',
            'closed_at' => in_array($state, ['Dismissed', 'TriggerCleared', 'EvidenceChanged', 'Terminated'], true) ? now() : null,
        ]);
    }
    Sanctum::actingAs($actor);
    $url = '/api/v1/academic-standing/reports/termination';
    $this->getJson($url.'?per_page=1')->assertOk()
        ->assertJsonPath('data.case_count', 4)->assertJsonPath('data.student_count', 4)
        ->assertJsonPath('data.states.AwaitingAdvisor', 1)->assertJsonPath('data.states.AwaitingDepartmentHead', 1)
        ->assertJsonPath('data.states.AwaitingCouncil', 1)->assertJsonPath('data.states.Terminated', 1)
        ->assertJsonPath('data.states.EvidenceChanged', 0);
    $this->getJson($url.'?history=include')->assertOk()->assertJsonPath('data.case_count', 7)->assertJsonPath('data.student_count', 4);
    $response = $this->getJson($url.'/cases?history=include')->assertOk()->assertJsonPath('meta.total', 7);
    foreach ($response->json('data') as $row) {
        expect($row)->not->toHaveKeys(['fingerprint', 'evidence', 'advisor_assessment', 'advisor_recommendation', 'department_head_reason', 'council_reason', 'national_id', 'identification_number']);
    }
    expect($response->getContent())->not->toContain('PRIVATE-', 'DO-NOT-DISCLOSE');
    $this->getJson($url)->assertOk();
    expect(TerminationCase::query()->count())->toBe(7)
        ->and(StandingObservation::query()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0)
        ->and(Student::query()->where('lifecycle_status', 'Terminated')->count())->toBe(1);
});

it('enforces the live Advisor Dean union and inclusive UTC Case-created dates before stable paging', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::Advisor);
    $actor->assignRole(Role::Dean);
    $program = Program::factory()->create();
    $outside = Program::factory()->create();
    $assignment = FacultyDeanAssignment::factory()->create(['user_id' => $actor->id, 'faculty_id' => $program->department->faculty_id]);
    $facultyStudent = Student::factory()->create(['program_id' => $program->id]);
    $advisee = Student::factory()->create(['program_id' => $outside->id, 'advisor_id' => $actor->id]);
    $hidden = Student::factory()->create(['program_id' => $outside->id]);
    $cases = [];
    foreach ([[$facultyStudent, '2026-01-03 00:00:00'], [$advisee, '2026-01-03 23:59:59'], [$advisee, '2026-01-04 00:00:00'], [$hidden, '2026-01-03 12:00:00']] as [$student, $created]) {
        $case = new TerminationCase;
        $case->forceFill(['student_id' => $student->id, 'fingerprint' => str_pad((string) count($cases), 64, 'c'), 'state' => 'EvidenceChanged', 'evidence' => [], 'closed_at' => now(), 'created_at' => $created, 'updated_at' => $created])->save();
        $cases[] = $case;
    }
    Sanctum::actingAs($actor);
    $url = '/api/v1/academic-standing/reports/termination';
    $criteria = '?history=include&created_from=2026-01-03&created_to=2026-01-03&sort=-created_at&per_page=1';
    $this->getJson($url.'/cases'.$criteria)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.case_id', $cases[1]->id);
    $this->getJson($url.'/cases'.$criteria.'&page=2')->assertOk()->assertJsonPath('data.0.case_id', $cases[0]->id);
    $this->getJson($url.$criteria)->assertOk()->assertJsonPath('data.case_count', 2)->assertJsonPath('data.student_count', 2);
    $assignment->delete();
    $this->getJson($url.$criteria)->assertOk()->assertJsonPath('data.case_count', 1);
    $advisee->update(['advisor_id' => User::factory()->create()->id]);
    $this->getJson($url.$criteria)->assertOk()->assertJsonPath('data.case_count', 0);
    $this->getJson($url.'?created_from=invalid')->assertUnprocessable();
    $this->getJson($url.'?national_id=123')->assertUnprocessable();
});

it('offers operational review links only for the actors actual stage and live Case access', function () {
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $advisor->assignRole(Role::Dean);
    $program = Program::factory()->create();
    FacultyDeanAssignment::factory()->create(['user_id' => $advisor->id, 'faculty_id' => $program->department->faculty_id]);
    $own = Student::factory()->create(['program_id' => $program->id, 'advisor_id' => $advisor->id]);
    $facultyOnly = Student::factory()->create(['program_id' => $program->id]);
    foreach ([$own, $facultyOnly] as $student) {
        TerminationCase::query()->create(['student_id' => $student->id, 'fingerprint' => str_pad((string) $student->id, 64, 'd'), 'state' => 'AwaitingAdvisor', 'evidence' => []]);
    }
    Sanctum::actingAs($advisor);
    $url = '/api/v1/academic-standing/reports/termination/cases';
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.review_url', '/termination/advisor')->assertJsonPath('data.1.review_url', null);
    $oversight = User::factory()->create();
    $oversight->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($oversight);
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.review_url', null)->assertJsonPath('data.1.review_url', null);
    $this->getJson('/api/v1/termination-cases/advisor')->assertForbidden();
});

it('denies cohort Case APIs and exports to a Student-only or Instructor-only permission holder', function (Role $role) {
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'standing.monitor' ? true : null);
    Sanctum::actingAs($actor);
    foreach (['', '/cases', '/cases/export'] as $suffix) {
        $this->getJson('/api/v1/academic-standing/reports/termination'.$suffix)->assertForbidden();
    }
})->with([Role::Student, Role::Instructor]);

it('restricts Head and Council workflow links to live academic assignment stage and Active lifecycle', function () {
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    $head->assignRole(Role::StudentAffairs);
    $program = Program::factory()->create();
    $outside = Program::factory()->create();
    $assignment = DepartmentHeadAssignment::factory()->create(['user_id' => $head->id, 'department_id' => $program->department_id]);
    $own = Student::factory()->create(['program_id' => $program->id]);
    $other = Student::factory()->create(['program_id' => $outside->id]);
    $historical = Student::factory()->create(['program_id' => $program->id, 'lifecycle_status' => 'Terminated']);
    $cases = [];
    foreach ([$own, $other, $historical] as $student) {
        $cases[] = TerminationCase::query()->create(['student_id' => $student->id, 'fingerprint' => str_pad((string) $student->id, 64, 'f'), 'state' => 'AwaitingDepartmentHead', 'evidence' => []]);
    }
    Sanctum::actingAs($head);
    $url = '/api/v1/academic-standing/reports/termination/cases';
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.review_url', '/termination/department-head')
        ->assertJsonPath('data.1.review_url', null)->assertJsonPath('data.2.review_url', null);
    $assignment->delete();
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.review_url', null);
    $cases[0]->update(['state' => 'AwaitingCouncil']);
    $council = User::factory()->create();
    $council->assignRole(Role::Council);
    Sanctum::actingAs($council);
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.review_url', '/termination/council')->assertJsonPath('data.1.review_url', null);
    $cases[0]->update(['state' => 'Terminated', 'closed_at' => now()]);
    $this->getJson($url)->assertOk()->assertJsonPath('data.0.review_url', null);
});

it('caps pages across bounded cohorts and breaks equal stage timestamps by stable Case ID', function () {
    $actor = User::factory()->create();
    $actor->assignRole(Role::Council);
    $students = Student::factory()->count(105)->create(['program_id' => 999999]);
    $ids = [];
    foreach ($students as $student) {
        $case = new TerminationCase;
        $case->forceFill(['student_id' => $student->id, 'fingerprint' => str_pad((string) $student->id, 64, 'g'),
            'state' => 'EvidenceChanged', 'evidence' => [], 'closed_at' => '2026-01-03 12:00:00',
            'created_at' => '2026-01-03 12:00:00', 'updated_at' => '2026-01-03 12:00:00'])->save();
        $ids[] = $case->id;
    }
    Sanctum::actingAs($actor);
    $url = '/api/v1/academic-standing/reports/termination/cases?history=include&sort=-created_at&per_page=999';
    $first = $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 105)->assertJsonPath('meta.per_page', 100)->assertJsonPath('meta.last_page', 2);
    expect(array_column($first->json('data'), 'case_id'))->toBe(array_slice($ids, 0, 100));
    $second = $this->getJson($url.'&page=2')->assertOk()->assertJsonCount(5, 'data');
    expect(array_column($second->json('data'), 'case_id'))->toBe(array_slice($ids, 100));
    $this->getJson('/api/v1/academic-standing/reports/termination?history=include&per_page=1')->assertOk()
        ->assertJsonPath('data.case_count', 105)->assertJsonPath('data.student_count', 105);
});
