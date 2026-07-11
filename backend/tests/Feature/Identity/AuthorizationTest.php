<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('exposes the effective permissions for the signed-in user from /me', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::DepartmentHead);
    Sanctum::actingAs($user);

    $permissions = $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.roles.0.code', 'R06')
        ->json('data.permissions');

    expect($permissions)
        ->toContain('catalog.manage')      // R06 holds it
        ->not->toContain('application.submit'); // R06 does not
});

it('returns 200 from the gated endpoint for a role that holds the permission', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::DepartmentHead); // holds catalog.manage
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/demo/catalog-management')
        ->assertOk()
        ->assertJsonPath('data.message', 'You can manage programs and courses.');
});

it('returns 403 from the gated endpoint for a role that lacks the permission', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Applicant); // does not hold catalog.manage
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/demo/catalog-management')->assertForbidden();
});

it('lets an administrator through the gate via the blanket permission', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::SystemAdmin); // R09 holds every permission
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/demo/catalog-management')->assertOk();
});
