<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('lets a viewer list faculties', function () {
    Faculty::factory()->count(2)->create();
    actingAsRole(Role::Applicant); // holds catalog.view, not catalog.manage

    $this->getJson('/api/v1/faculties')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'code', 'name_en', 'name_ar', 'is_active']]]);
});

it('lets a viewer read a single faculty with both language names', function () {
    $faculty = Faculty::factory()->create(['name_en' => 'Faculty of Engineering', 'name_ar' => 'كلية الهندسة']);
    actingAsRole(Role::Student);

    $this->getJson("/api/v1/faculties/{$faculty->id}")
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Faculty of Engineering')
        ->assertJsonPath('data.name_ar', 'كلية الهندسة');
});

it('lets a manager create a faculty, active by default', function () {
    actingAsRole(Role::DepartmentHead); // holds catalog.manage

    $this->postJson('/api/v1/faculties', [
        'code' => 'FoE',
        'name_en' => 'Faculty of Engineering',
        'name_ar' => 'كلية الهندسة',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'FoE')
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('faculties', ['code' => 'FoE', 'is_active' => true]);
});

it('requires both language names', function () {
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/faculties', ['code' => 'FoE', 'name_en' => 'Faculty of Engineering'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name_ar');
});

it('rejects a duplicate faculty code', function () {
    Faculty::factory()->create(['code' => 'FoE']);
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/faculties', [
        'code' => 'FoE',
        'name_en' => 'Duplicate',
        'name_ar' => 'مكرر',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('lets a manager rename a faculty', function () {
    $faculty = Faculty::factory()->create(['code' => 'FoE', 'name_en' => 'Old']);
    actingAsRole(Role::Dean); // also holds catalog.manage

    $this->patchJson("/api/v1/faculties/{$faculty->id}", [
        'code' => 'FoE',
        'name_en' => 'Faculty of Engineering',
        'name_ar' => 'كلية الهندسة',
    ])
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Faculty of Engineering');

    $this->assertDatabaseHas('faculties', ['id' => $faculty->id, 'name_en' => 'Faculty of Engineering']);
});

it('lets a manager deactivate then reactivate a faculty', function () {
    $faculty = Faculty::factory()->create(['is_active' => true]);
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/faculties/{$faculty->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
    $this->assertDatabaseHas('faculties', ['id' => $faculty->id, 'is_active' => false]);

    $this->postJson("/api/v1/faculties/{$faculty->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('forbids mutations for a viewer without catalog.manage', function () {
    $faculty = Faculty::factory()->create();
    actingAsRole(Role::Applicant); // catalog.view only

    $this->postJson('/api/v1/faculties', ['code' => 'X', 'name_en' => 'x', 'name_ar' => 'س'])
        ->assertForbidden();
    $this->patchJson("/api/v1/faculties/{$faculty->id}", ['code' => 'X', 'name_en' => 'x', 'name_ar' => 'س'])
        ->assertForbidden();
    $this->postJson("/api/v1/faculties/{$faculty->id}/deactivate")
        ->assertForbidden();
});

it('does not allow hard-deleting a faculty', function () {
    $faculty = Faculty::factory()->create();
    actingAsRole(Role::DepartmentHead);

    // Only GET/PATCH exist on this URI; DELETE is deactivate-only by design.
    $this->deleteJson("/api/v1/faculties/{$faculty->id}")->assertStatus(405);
});

it('requires authentication to read the catalog', function () {
    $this->getJson('/api/v1/faculties')->assertUnauthorized();
});
