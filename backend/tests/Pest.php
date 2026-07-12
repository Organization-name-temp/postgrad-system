<?php

use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use Tests\TestCase;

/*
| Bind Laravel's TestCase to the Feature suite so feature tests can drive the
| HTTP kernel ($this->getJson(...), etc.). Database-touching tests opt into
| RefreshDatabase per file (from ticket 04 on); Phase 0's tracer needs no DB, so
| it is not applied globally here.
*/

uses(TestCase::class)->in('Feature');

/**
 * Sign in as a fresh user holding the given role. Shared across feature tests
 * (the API is the sole RBAC authority — ADR-0008).
 */
function actingAsRole(Role $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);
    Sanctum::actingAs($user);

    return $user;
}
