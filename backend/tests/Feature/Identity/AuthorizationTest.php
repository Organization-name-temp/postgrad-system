<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Models\Course;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

// The RBAC gate is exercised end-to-end against a real permission-gated endpoint —
// activating a Course, which requires catalog.manage. (Phase 0 used a throwaway
// /demo/catalog-management endpoint for this; Phase 1's real Catalog module
// superseded it, so the proof now rides a real consumer.)

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

it('returns 200 from a gated endpoint for a role that holds the permission', function () {
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(Role::DepartmentHead); // holds catalog.manage
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/courses/{$course->id}/activate")->assertOk();
});

it('returns 403 from a gated endpoint for a role that lacks the permission', function () {
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(Role::Applicant); // holds catalog.view, not catalog.manage
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/courses/{$course->id}/activate")->assertForbidden();
});

it('lets an administrator through the gate via the blanket permission', function () {
    $course = Course::factory()->create();
    $user = User::factory()->create();
    $user->assignRole(Role::SystemAdmin); // R09 holds every permission
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/courses/{$course->id}/activate")->assertOk();
});
