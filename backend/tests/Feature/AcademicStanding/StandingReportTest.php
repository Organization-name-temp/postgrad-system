<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('summarizes only the live union of permitted active Students and narrows it with academic criteria', function () {
    $reviewer = User::factory()->create();
    $reviewer->assignRole(Role::Advisor);
    $reviewer->assignRole(Role::Dean);
    $program = Program::factory()->create();
    $otherProgram = Program::factory()->create();
    $assignment = FacultyDeanAssignment::factory()->create([
        'user_id' => $reviewer->id, 'faculty_id' => $program->department->faculty_id,
    ]);
    Student::factory()->create(['program_id' => $program->id]);
    Student::factory()->create(['program_id' => $otherProgram->id, 'advisor_id' => $reviewer->id]);
    Student::factory()->create(['program_id' => $otherProgram->id]);
    Student::factory()->create(['program_id' => $program->id, 'lifecycle_status' => 'Terminated']);
    Sanctum::actingAs($reviewer);
    $this->getJson('/api/v1/academic-standing/reports/standing')
        ->assertOk()->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.counts.NotYetAssessed', 2)
        ->assertJsonPath('data.counts.GoodStanding', 0)
        ->assertJsonCount(5, 'data.counts');
    $this->getJson('/api/v1/academic-standing/reports/standing?program_id='.$program->id)
        ->assertOk()->assertJsonPath('data.total', 1);
    $this->getJson('/api/v1/academic-standing/reports/standing?lifecycle=Terminated')
        ->assertOk()->assertJsonPath('data.total', 1);
    $assignment->delete();
    $this->getJson('/api/v1/academic-standing/reports/standing')
        ->assertOk()->assertJsonPath('data.total', 1);
});

it('denies Student-only and Instructor-only cohort access even with standing.monitor', function (Role $role) {
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Gate::before(fn (User $user, string $ability): ?bool => $ability === 'standing.monitor' ? true : null);
    Sanctum::actingAs($actor);
    $this->getJson('/api/v1/academic-standing/reports/standing')->assertForbidden();
})->with([Role::Student, Role::Instructor]);

it('allows university oversight Roles while keeping lifecycle separate from Standing', function (Role $role) {
    $actor = User::factory()->create();
    $actor->assignRole($role);
    Student::factory()->count(2)->create();
    Student::factory()->create(['lifecycle_status' => 'Terminated']);
    Sanctum::actingAs($actor);
    $this->getJson('/api/v1/academic-standing/reports/standing?per_page=1')
        ->assertOk()->assertJsonPath('data.total', 2);
    $this->getJson('/api/v1/academic-standing/reports/standing?lifecycle=Terminated')
        ->assertOk()->assertJsonPath('data.total', 1)
        ->assertJsonPath('data.counts.NotYetAssessed', 1);
})->with([Role::StudentAffairs, Role::Council, Role::SystemAdmin, Role::SuperAdmin]);

it('restricts Head counts to live academic Departments and rejects unknown criteria', function () {
    $head = User::factory()->create();
    $head->assignRole(Role::DepartmentHead);
    $program = Program::factory()->create();
    $outside = Program::factory()->create();
    $assignment = DepartmentHeadAssignment::factory()->create([
        'user_id' => $head->id, 'department_id' => $program->department_id,
    ]);
    DepartmentHeadAssignment::factory()->universityWide()->create(['user_id' => $head->id]);
    Student::factory()->create(['program_id' => $program->id, 'name_ar' => 'أَحمد علي']);
    Student::factory()->create(['program_id' => $outside->id]);
    Sanctum::actingAs($head);
    $this->getJson('/api/v1/academic-standing/reports/standing?q=احمد')
        ->assertOk()->assertJsonPath('data.total', 1);
    $this->getJson('/api/v1/academic-standing/reports/standing?program_id='.$outside->id)
        ->assertOk()->assertJsonPath('data.total', 0);
    $this->getJson('/api/v1/academic-standing/reports/standing?private_evidence=yes')->assertUnprocessable();
    $this->getJson('/api/v1/academic-standing/reports/standing?faculty_id=-1')->assertUnprocessable();
    $assignment->delete();
    $this->getJson('/api/v1/academic-standing/reports/standing')
        ->assertOk()->assertJsonPath('data.total', 0);
});
