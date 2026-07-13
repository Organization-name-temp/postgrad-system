<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('lets a viewer list programs, each carrying its per-category credit requirements', function () {
    Program::factory()->count(2)->create();
    actingAsRole(Role::Applicant); // holds catalog.view, not catalog.manage

    $this->getJson('/api/v1/programs')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [[
            'id', 'department_id', 'code', 'name_en', 'name_ar', 'degree_level',
            'prep_required_ch', 'core_required_ch', 'elective_required_ch',
            'total_required_ch', 'is_active',
        ]]]);
});

it('lets a viewer read a single program with both names and its credit split', function () {
    $program = Program::factory()->create([
        'name_en' => 'MSc in Computer Engineering',
        'name_ar' => 'ماجستير في هندسة الحاسبات',
        'prep_required_ch' => 6,
        'core_required_ch' => 6,
        'elective_required_ch' => 9,
    ]);
    actingAsRole(Role::Student);

    $this->getJson("/api/v1/programs/{$program->id}")
        ->assertOk()
        ->assertJsonPath('data.name_en', 'MSc in Computer Engineering')
        ->assertJsonPath('data.name_ar', 'ماجستير في هندسة الحاسبات')
        ->assertJsonPath('data.prep_required_ch', 6)
        ->assertJsonPath('data.core_required_ch', 6)
        ->assertJsonPath('data.elective_required_ch', 9)
        ->assertJsonPath('data.total_required_ch', 21);
});

it('lets a manager create a program with the default 6/6/9 split applied, active by default', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead); // holds catalog.manage

    $this->postJson('/api/v1/programs', [
        'department_id' => $department->id,
        'code' => 'MSC-CE',
        'name_en' => 'MSc in Computer Engineering',
        'name_ar' => 'ماجستير في هندسة الحاسبات',
        'degree_level' => 'MSc',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'MSC-CE')
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.degree_level', 'MSc')
        ->assertJsonPath('data.prep_required_ch', 6)
        ->assertJsonPath('data.core_required_ch', 6)
        ->assertJsonPath('data.elective_required_ch', 9)
        ->assertJsonPath('data.total_required_ch', 21)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('programs', [
        'code' => 'MSC-CE', 'department_id' => $department->id,
        'prep_required_ch' => 6, 'core_required_ch' => 6, 'elective_required_ch' => 9,
        'is_active' => true,
    ]);
});

it('lets a manager create a program with a custom credit split, total derived from it', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/programs', [
        'department_id' => $department->id,
        'code' => 'PHD-CE',
        'name_en' => 'PhD in Computer Engineering',
        'name_ar' => 'دكتوراه في هندسة الحاسبات',
        'degree_level' => 'PhD',
        'prep_required_ch' => 3,
        'core_required_ch' => 9,
        'elective_required_ch' => 12,
    ])
        ->assertCreated()
        ->assertJsonPath('data.prep_required_ch', 3)
        ->assertJsonPath('data.core_required_ch', 9)
        ->assertJsonPath('data.elective_required_ch', 12)
        ->assertJsonPath('data.total_required_ch', 24);
});

it('requires a department', function () {
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/programs', [
        'code' => 'MSC-CE', 'name_en' => 'MSc CE', 'name_ar' => 'ماجستير', 'degree_level' => 'MSc',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');
});

it('rejects a non-existent department', function () {
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/programs', [
        'department_id' => 999999, 'code' => 'MSC-CE',
        'name_en' => 'MSc CE', 'name_ar' => 'ماجستير', 'degree_level' => 'MSc',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');
});

it('requires both language names', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/programs', [
        'department_id' => $department->id, 'code' => 'MSC-CE', 'degree_level' => 'MSc',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name_en', 'name_ar']);
});

it('requires the degree level and constrains it to the MSc/PhD enum', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    // Missing
    $this->postJson('/api/v1/programs', [
        'department_id' => $department->id, 'code' => 'MSC-CE',
        'name_en' => 'MSc CE', 'name_ar' => 'ماجستير',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('degree_level');

    // Not one of the enum values
    $this->postJson('/api/v1/programs', [
        'department_id' => $department->id, 'code' => 'MSC-CE',
        'name_en' => 'MSc CE', 'name_ar' => 'ماجستير', 'degree_level' => 'Diploma',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('degree_level');
});

it('rejects a duplicate program code', function () {
    Program::factory()->create(['code' => 'MSC-CE']);
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/programs', [
        'department_id' => $department->id, 'code' => 'MSC-CE',
        'name_en' => 'Duplicate', 'name_ar' => 'مكرر', 'degree_level' => 'MSc',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('rejects a negative credit-hour requirement', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/programs', [
        'department_id' => $department->id, 'code' => 'MSC-CE',
        'name_en' => 'MSc CE', 'name_ar' => 'ماجستير', 'degree_level' => 'MSc',
        'prep_required_ch' => -1,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('prep_required_ch');
});

it('lets a manager edit a program name and credit split', function () {
    $program = Program::factory()->create(['code' => 'MSC-CE', 'name_en' => 'Old', 'core_required_ch' => 6]);
    actingAsRole(Role::Dean);

    $this->patchJson("/api/v1/programs/{$program->id}", [
        'department_id' => $program->department_id,
        'code' => 'MSC-CE',
        'name_en' => 'MSc in Computer Engineering',
        'name_ar' => 'ماجستير في هندسة الحاسبات',
        'degree_level' => 'MSc',
        'core_required_ch' => 12,
    ])
        ->assertOk()
        ->assertJsonPath('data.name_en', 'MSc in Computer Engineering')
        ->assertJsonPath('data.core_required_ch', 12);

    $this->assertDatabaseHas('programs', ['id' => $program->id, 'name_en' => 'MSc in Computer Engineering', 'core_required_ch' => 12]);
});

it('lets a manager deactivate then reactivate a program, leaving it readable in between (BR-18)', function () {
    $program = Program::factory()->create(['is_active' => true]);
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/programs/{$program->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
    $this->assertDatabaseHas('programs', ['id' => $program->id, 'is_active' => false]);

    // Deactivating does not delete or hide it — a viewer can still read it.
    actingAsRole(Role::Applicant);
    $this->getJson("/api/v1/programs/{$program->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    actingAsRole(Role::DepartmentHead);
    $this->postJson("/api/v1/programs/{$program->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('forbids mutations for a viewer without catalog.manage', function () {
    $program = Program::factory()->create();
    $payload = [
        'department_id' => $program->department_id, 'code' => 'X',
        'name_en' => 'x', 'name_ar' => 'س', 'degree_level' => 'MSc',
    ];
    actingAsRole(Role::Applicant); // catalog.view only

    $this->postJson('/api/v1/programs', $payload)->assertForbidden();
    $this->patchJson("/api/v1/programs/{$program->id}", $payload)->assertForbidden();
    $this->postJson("/api/v1/programs/{$program->id}/activate")->assertForbidden();
    $this->postJson("/api/v1/programs/{$program->id}/deactivate")->assertForbidden();
});

it('does not allow hard-deleting a program', function () {
    $program = Program::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->deleteJson("/api/v1/programs/{$program->id}")->assertStatus(405);
});
