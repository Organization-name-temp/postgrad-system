<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Catalog Summary Card (phase 4.5, ticket 06) — the second configuration Card: how many Programs
| and Courses are active, answering "is the catalog populated?" without a navigation.
|
| Two aggregate counts and a permission gate, so what is asserted is exactly that: that
| deactivated rows are excluded, that an empty catalog reads as zeros rather than an error, and
| who is allowed to read it. Self-contained helper (cscc* prefix) so a single-file run works.
*/

function csccCard()
{
    return test()->getJson('/api/v1/catalog/cards/summary');
}

// ---------------------------------------------------------------------------
// The counts
// ---------------------------------------------------------------------------

it('counts the active programs and courses, excluding the deactivated ones', function () {
    Program::factory()->count(2)->create(['is_active' => true]);
    Program::factory()->create(['is_active' => false]);
    Course::factory()->count(3)->create(['is_active' => true]);
    Course::factory()->count(2)->create(['is_active' => false]);

    actingAsRole(Role::DepartmentHead); // holds catalog.manage (§3.2)

    csccCard()->assertOk()
        ->assertJsonPath('data.active_program_count', 2)
        ->assertJsonPath('data.active_course_count', 3);
});

it('returns zeros for an empty catalog rather than erroring', function () {
    actingAsRole(Role::DepartmentHead);

    csccCard()->assertOk()
        ->assertJsonPath('data.active_program_count', 0)
        ->assertJsonPath('data.active_course_count', 0);
});

// ---------------------------------------------------------------------------
// Authorization — the server decides, not the client's choice of what to request
// ---------------------------------------------------------------------------

it('refuses a user who holds only catalog.view, not catalog.manage', function () {
    // A Card about how the catalog is *configured* belongs to the people who configure it, so
    // the read permission every academic role holds is deliberately not enough.
    actingAsRole(Role::Student);

    csccCard()->assertForbidden();
});

it('serves an administrator, who inherits catalog.manage', function () {
    actingAsRole(Role::SystemAdmin);

    csccCard()->assertOk();
});

it('refuses an unauthenticated request', function () {
    csccCard()->assertUnauthorized();
});
