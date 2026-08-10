<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\DepartmentHeadAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Department-Head↔Department assignment admin surface (phase 4, ticket 02). Shaped
| and gated exactly like the Dean↔Faculty surface of Phase 2: role-gated on R09/R10
| (no new permission, ADR-0008 intact), Departments named by opaque Catalog id with
| no FK (ADR-0012).
|
| The one thing that is genuinely new: the Department reference is **nullable**, and
| NULL is not "unassigned" — it is the university-wide Preparatory scope, mirroring
| Course.department_id (ADR-0010). Because NULL is a real value here, the uniqueness
| rule has to bite on it too, and every read has to reach it. Both are asserted below.
*/

/** A fresh Department Head (R06) user. */
function headUser(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::DepartmentHead);

    return $user;
}

it('lets an administrator list Department-Head assignments', function () {
    $head = headUser();
    DepartmentHeadAssignment::create(['user_id' => $head->id, 'department_id' => 3]);
    actingAsRole(Role::SystemAdmin);

    $this->getJson('/api/v1/admin/department-head-assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.user_id', $head->id)
        ->assertJsonPath('data.0.department_id', 3)
        ->assertJsonPath('data.0.university_wide', false)
        ->assertJsonPath('data.0.head_name', $head->name);
});

it('lets an administrator assign a Head to a department', function () {
    $head = headUser();
    actingAsRole(Role::SuperAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $head->id,
        'department_id' => 5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.user_id', $head->id)
        ->assertJsonPath('data.department_id', 5);

    $this->assertDatabaseHas('department_head_assignments', [
        'user_id' => $head->id,
        'department_id' => 5,
    ]);
});

it('lets an administrator assign a Head to the university-wide scope', function () {
    $head = headUser();
    actingAsRole(Role::SystemAdmin);

    // NULL is the university-wide Preparatory scope (ADR-0023), chosen explicitly —
    // it is a value the client sends, not a field it omits.
    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $head->id,
        'department_id' => null,
    ])
        ->assertCreated()
        ->assertJsonPath('data.department_id', null)
        // The payload names the scope rather than leaving a reader to infer it from
        // the null — the contract-level half of the ticket's "explicit named choice".
        ->assertJsonPath('data.university_wide', true);

    $this->assertDatabaseHas('department_head_assignments', [
        'user_id' => $head->id,
        'department_id' => null,
    ]);
});

it('requires the scope to be stated rather than omitted (422)', function () {
    $head = headUser();
    actingAsRole(Role::SystemAdmin);

    // An absent department_id is not university-wide: the scope is an explicit choice,
    // so a missing key is a client error rather than a silent NULL.
    $this->postJson('/api/v1/admin/department-head-assignments', ['user_id' => $head->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');

    $this->assertDatabaseCount('department_head_assignments', 0);
});

it('refuses to assign a user who is not a Department Head (422)', function () {
    $notAHead = User::factory()->create(); // no R06
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $notAHead->id,
        'department_id' => 5,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');

    $this->assertDatabaseCount('department_head_assignments', 0);
});

it('returns the not-a-Head refusal in Arabic when Arabic is requested', function () {
    $notAHead = User::factory()->create();
    actingAsRole(Role::SystemAdmin);

    $this->withHeader('Accept-Language', 'ar')
        ->postJson('/api/v1/admin/department-head-assignments', [
            'user_id' => $notAHead->id,
            'department_id' => 5,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.user_id.0', __('identity.assignment.not_a_head', locale: 'ar'));
});

it('refuses a duplicate (Head, department) pair (422)', function () {
    $head = headUser();
    DepartmentHeadAssignment::create(['user_id' => $head->id, 'department_id' => 5]);
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $head->id,
        'department_id' => 5,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');

    $this->assertDatabaseCount('department_head_assignments', 1);
});

it('refuses a second university-wide assignment for the same Head (422)', function () {
    // The NULL case of the same rule. SQL's default "NULLs are distinct" would let this
    // through, so the constraint and the check both have to treat NULL as a value.
    $head = headUser();
    DepartmentHeadAssignment::create(['user_id' => $head->id, 'department_id' => null]);
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $head->id,
        'department_id' => null,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');

    $this->assertDatabaseCount('department_head_assignments', 1);
});

it('lets a Head who already covers a department also cover the university-wide scope', function () {
    $head = headUser();
    DepartmentHeadAssignment::create(['user_id' => $head->id, 'department_id' => 5]);
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $head->id,
        'department_id' => null,
    ])->assertCreated();

    $this->assertDatabaseCount('department_head_assignments', 2);
});

it('lets two different Heads cover the same department', function () {
    $first = headUser();
    $second = headUser();
    DepartmentHeadAssignment::create(['user_id' => $first->id, 'department_id' => 5]);
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $second->id,
        'department_id' => 5,
    ])->assertCreated();

    $this->assertDatabaseCount('department_head_assignments', 2);
});

it('lets an administrator unassign a Head from a department', function () {
    $head = headUser();
    $assignment = DepartmentHeadAssignment::create(['user_id' => $head->id, 'department_id' => 5]);
    actingAsRole(Role::SystemAdmin);

    $this->deleteJson("/api/v1/admin/department-head-assignments/{$assignment->id}")
        ->assertNoContent();

    $this->assertDatabaseCount('department_head_assignments', 0);
});

it('lists assignable Department Heads for the admin picker', function () {
    $head = headUser();
    User::factory()->create(); // a non-Head must not appear
    actingAsRole(Role::SystemAdmin);

    $this->getJson('/api/v1/admin/department-heads')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $head->id)
        ->assertJsonPath('data.0.name', $head->name);
});

// ---------------------------------------------------------------------------
// Audit — every write to the routing map is accountable (ticket 02)
// ---------------------------------------------------------------------------

it('audits an assignment', function () {
    $head = headUser();
    $admin = actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $head->id,
        'department_id' => 5,
    ])->assertCreated();

    $this->assertDatabaseHas('audit_log', [
        'action' => 'department_head.assigned',
        'actor_user_id' => $admin->id,
        'subject_type' => 'department_head_assignment',
    ]);
});

it('audits an unassignment', function () {
    $head = headUser();
    $assignment = DepartmentHeadAssignment::create(['user_id' => $head->id, 'department_id' => null]);
    $admin = actingAsRole(Role::SystemAdmin);

    $this->deleteJson("/api/v1/admin/department-head-assignments/{$assignment->id}")
        ->assertNoContent();

    $this->assertDatabaseHas('audit_log', [
        'action' => 'department_head.unassigned',
        'actor_user_id' => $admin->id,
        'subject_type' => 'department_head_assignment',
        'subject_id' => (string) $assignment->id,
    ]);
});

it('does not audit a refused assignment', function () {
    $notAHead = User::factory()->create();
    actingAsRole(Role::SystemAdmin);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $notAHead->id,
        'department_id' => 5,
    ])->assertStatus(422);

    $this->assertDatabaseCount('audit_log', 0);
});

// ---------------------------------------------------------------------------
// The R09/R10 gate — role, not permission (ADR-0008)
// ---------------------------------------------------------------------------

it('forbids a non-administrator from reading assignments (403)', function () {
    DepartmentHeadAssignment::create(['user_id' => headUser()->id, 'department_id' => 3]);
    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/admin/department-head-assignments')->assertForbidden();
    $this->getJson('/api/v1/admin/department-heads')->assertForbidden();
});

it('forbids a Department Head from changing assignments (403)', function () {
    // R06 is the subject of the mapping, never its author — administration is gated on
    // R09/R10 alone.
    $actingHead = headUser();
    Sanctum::actingAs($actingHead);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => $actingHead->id,
        'department_id' => 5,
    ])->assertForbidden();

    $this->assertDatabaseCount('department_head_assignments', 0);
});

it('forbids a Dean from changing assignments (403)', function () {
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    Sanctum::actingAs($dean);

    $this->postJson('/api/v1/admin/department-head-assignments', [
        'user_id' => headUser()->id,
        'department_id' => 5,
    ])->assertForbidden();
});

it('forbids an unauthenticated visitor (401)', function () {
    $this->getJson('/api/v1/admin/department-head-assignments')->assertUnauthorized();
});
