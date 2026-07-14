<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Dean↔Faculty assignment admin surface (ticket 08) — the first role-gated (not
| permission-gated) surface. Driven end-to-end through /api/v1: only R09/R10 may
| read or change the mapping; everyone else, including a Dean, is refused (403).
| Faculties are opaque ids (no FK, ADR-0012).
*/

/** A fresh Dean (R07) user. */
function deanUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Dean);

    return $user;
}

it('lets an administrator list Dean↔Faculty assignments', function () {
    $dean = deanUser();
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => 3]);
    actingAsRole(Role::SystemAdmin);

    $this->getJson('/api/v1/admin/faculty-dean-assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $dean->id)
        ->assertJsonPath('data.0.faculty_id', 3)
        ->assertJsonPath('data.0.dean_name', $dean->name);
});

it('lets an administrator assign a Dean to a faculty', function () {
    $dean = deanUser();
    actingAsRole(Role::SuperAdmin);

    $this->postJson('/api/v1/admin/faculty-dean-assignments', [
        'user_id' => $dean->id,
        'faculty_id' => 5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $dean->id)
        ->assertJsonPath('data.faculty_id', 5);

    $this->assertDatabaseHas('faculty_dean_assignments', [
        'user_id' => $dean->id,
        'faculty_id' => 5,
    ]);
});

it('refuses to assign a user who is not a Dean (422)', function () {
    $notADean = User::factory()->create(); // no R07
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/faculty-dean-assignments', [
        'user_id' => $notADean->id,
        'faculty_id' => 5,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');

    $this->assertDatabaseCount('faculty_dean_assignments', 0);
});

it('refuses a duplicate (Dean, faculty) pair (422)', function () {
    $dean = deanUser();
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => 5]);
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/faculty-dean-assignments', [
        'user_id' => $dean->id,
        'faculty_id' => 5,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('faculty_id');

    $this->assertDatabaseCount('faculty_dean_assignments', 1);
});

it('lets the same Dean cover more than one faculty', function () {
    $dean = deanUser();
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => 5]);
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/faculty-dean-assignments', [
        'user_id' => $dean->id,
        'faculty_id' => 6,
    ])->assertCreated();

    $this->assertDatabaseCount('faculty_dean_assignments', 2);
});

it('lets an administrator unassign a Dean from a faculty', function () {
    $dean = deanUser();
    $assignment = FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => 5]);
    actingAsRole(Role::SystemAdmin);

    $this->deleteJson("/api/v1/admin/faculty-dean-assignments/{$assignment->id}")
        ->assertNoContent();

    $this->assertDatabaseCount('faculty_dean_assignments', 0);
});

it('lists assignable Deans for the admin picker', function () {
    $dean = deanUser();
    User::factory()->create(); // a non-Dean must not appear
    actingAsRole(Role::SystemAdmin);

    $this->getJson('/api/v1/admin/deans')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $dean->id)
        ->assertJsonPath('data.0.name', $dean->name);
});

it('forbids a non-administrator from reading assignments (403)', function () {
    FacultyDeanAssignment::create(['user_id' => deanUser()->id, 'faculty_id' => 3]);
    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/admin/faculty-dean-assignments')->assertForbidden();
    $this->getJson('/api/v1/admin/deans')->assertForbidden();
});

it('forbids a Dean from changing assignments (403)', function () {
    // A Dean holds application.screen, but administration is role-gated on R09/R10.
    $actingDean = deanUser();
    Sanctum::actingAs($actingDean);

    $this->postJson('/api/v1/admin/faculty-dean-assignments', [
        'user_id' => $actingDean->id,
        'faculty_id' => 5,
    ])->assertForbidden();

    $this->assertDatabaseCount('faculty_dean_assignments', 0);
});

it('forbids an unauthenticated visitor (401/403)', function () {
    $this->getJson('/api/v1/admin/faculty-dean-assignments')->assertUnauthorized();
});
