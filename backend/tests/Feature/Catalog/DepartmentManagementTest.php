<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('lets a viewer list departments, each carrying its faculty id', function () {
    Department::factory()->count(2)->create();
    actingAsRole(Role::Applicant); // holds catalog.view, not catalog.manage

    $this->getJson('/api/v1/departments')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'faculty_id', 'code', 'name_en', 'name_ar', 'is_active']]]);
});

it('lets a viewer read a single department with both language names', function () {
    $department = Department::factory()->create(['name_en' => 'Computer Engineering', 'name_ar' => 'هندسة الحاسبات']);
    actingAsRole(Role::Student);

    $this->getJson("/api/v1/departments/{$department->id}")
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Computer Engineering')
        ->assertJsonPath('data.name_ar', 'هندسة الحاسبات');
});

it('lets a manager create a department under a faculty, active by default', function () {
    $faculty = Faculty::factory()->create();
    actingAsRole(Role::DepartmentHead); // holds catalog.manage

    $this->postJson('/api/v1/departments', [
        'faculty_id' => $faculty->id,
        'code' => 'CE',
        'name_en' => 'Computer Engineering',
        'name_ar' => 'هندسة الحاسبات',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'CE')
        ->assertJsonPath('data.faculty_id', $faculty->id)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('departments', ['code' => 'CE', 'faculty_id' => $faculty->id, 'is_active' => true]);
});

it('requires a faculty', function () {
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/departments', ['code' => 'CE', 'name_en' => 'Computer Engineering', 'name_ar' => 'هندسة الحاسبات'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('faculty_id');
});

it('rejects a non-existent faculty', function () {
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/departments', [
        'faculty_id' => 999999,
        'code' => 'CE',
        'name_en' => 'Computer Engineering',
        'name_ar' => 'هندسة الحاسبات',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('faculty_id');
});

it('requires both language names', function () {
    $faculty = Faculty::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/departments', ['faculty_id' => $faculty->id, 'code' => 'CE'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name_en', 'name_ar']);
});

it('rejects a duplicate department code', function () {
    Department::factory()->create(['code' => 'CE']);
    $faculty = Faculty::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/departments', [
        'faculty_id' => $faculty->id,
        'code' => 'CE',
        'name_en' => 'Duplicate',
        'name_ar' => 'مكرر',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('lets a manager rename a department', function () {
    $department = Department::factory()->create(['code' => 'CE', 'name_en' => 'Old']);
    actingAsRole(Role::Dean);

    $this->patchJson("/api/v1/departments/{$department->id}", [
        'faculty_id' => $department->faculty_id,
        'code' => 'CE',
        'name_en' => 'Computer Engineering',
        'name_ar' => 'هندسة الحاسبات',
    ])
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Computer Engineering');

    $this->assertDatabaseHas('departments', ['id' => $department->id, 'name_en' => 'Computer Engineering']);
});

it('lets a manager deactivate then reactivate a department', function () {
    $department = Department::factory()->create(['is_active' => true]);
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/departments/{$department->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
    $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => false]);

    $this->postJson("/api/v1/departments/{$department->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('forbids mutations for a viewer without catalog.manage', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::Applicant); // catalog.view only

    $this->postJson('/api/v1/departments', ['faculty_id' => $department->faculty_id, 'code' => 'X', 'name_en' => 'x', 'name_ar' => 'س'])
        ->assertForbidden();
    $this->patchJson("/api/v1/departments/{$department->id}", ['faculty_id' => $department->faculty_id, 'code' => 'X', 'name_en' => 'x', 'name_ar' => 'س'])
        ->assertForbidden();
    $this->postJson("/api/v1/departments/{$department->id}/activate")
        ->assertForbidden();
    $this->postJson("/api/v1/departments/{$department->id}/deactivate")
        ->assertForbidden();
});

it('does not allow hard-deleting a department', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->deleteJson("/api/v1/departments/{$department->id}")->assertStatus(405);
});
