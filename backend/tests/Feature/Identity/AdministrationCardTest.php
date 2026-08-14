<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\AuditLogger;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The Administration Dashboard Card (phase 4.5, ticket 07) — the one Card an administrator's
| landing page is about: how many Users and Role assignments exist, over the most recent Audit Log
| activity.
|
| It closes the phase's single deliberate exception to the composition rule, so the authority
| assertions carry more weight here than the payload does: this is the only Card gated on a **Role**
| that no one else can hold, and every other role — including the §3.2 holders of the permissions an
| administrator inherits — must be refused it. Written against the /api/v1 HTTP seam.
| Self-contained helpers (adc* prefix) so a single-file run works.
*/

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
});

function adcCard()
{
    return test()->getJson('/api/v1/admin/cards/administration');
}

/** Record one entry through the real append-only writer — the surface every module audits through. */
function adcEntry(string $action, ?User $actor, string $at): void
{
    Carbon::setTestNow(Carbon::parse($at));
    app(AuditLogger::class)->record($action, $actor?->id);
    Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
}

// ---------------------------------------------------------------------------
// The counts
// ---------------------------------------------------------------------------

it('counts the users and the role assignments they hold between them', function () {
    actingAsRole(Role::SystemAdmin);

    // A user holding two Roles is the fact the whole permission-union Dashboard rests on, so the
    // two counts are deliberately different numbers rather than synonyms.
    $multi = User::factory()->create();
    $multi->assignRole(Role::Advisor);
    $multi->assignRole(Role::Instructor);
    User::factory()->create(); // holds nothing at all

    adcCard()->assertOk()
        ->assertJsonPath('data.user_count', 3)
        ->assertJsonPath('data.role_assignment_count', 3); // the admin's one + the multi-role two
});

// ---------------------------------------------------------------------------
// Recent activity — a bounded read of the append-only log, not the Phase 7 Report
// ---------------------------------------------------------------------------

it('shows the most recent audit entries, newest first, as action keys and actors', function () {
    $admin = actingAsRole(Role::SystemAdmin);

    adcEntry('login', $admin, '2026-08-09 08:00:00');
    adcEntry('student.admitted', $admin, '2026-08-10 09:00:00');

    $response = adcCard()->assertOk()
        ->assertJsonPath('data.recent_activity.0.action', 'student.admitted')
        ->assertJsonPath('data.recent_activity.0.actor_user_id', $admin->id)
        ->assertJsonPath('data.recent_activity.0.actor_name', $admin->name)
        ->assertJsonPath('data.recent_activity.1.action', 'login');

    // Typed domain values only: an action travels as its key, never as a sentence the SPA would
    // have to display verbatim in whichever language the server happened to pick.
    expect($response->json('data.recent_activity.0'))
        ->toHaveKeys(['id', 'action', 'actor_user_id', 'actor_name', 'at']);
});

it('bounds the activity to the few newest entries rather than the whole log', function () {
    $admin = actingAsRole(Role::SystemAdmin);

    foreach (range(1, 6) as $day) {
        adcEntry('login', $admin, sprintf('2026-08-%02d 08:00:00', $day));
    }

    // A Card is fixed, scoped and summary; the filterable, paginated read of the whole log is the
    // Phase 7 Audit Log *Report*, which is a different object.
    adcCard()->assertOk()->assertJsonCount(3, 'data.recent_activity');
});

it('reports an entry with no actor rather than skipping it', function () {
    actingAsRole(Role::SystemAdmin);

    // A failed sign-in has no actor by construction — there is no authenticated user to name — and
    // it is exactly the kind of security-relevant event this Card exists to surface.
    adcEntry('login_failed', null, '2026-08-10 07:00:00');

    adcCard()->assertOk()
        ->assertJsonPath('data.recent_activity.0.action', 'login_failed')
        ->assertJsonPath('data.recent_activity.0.actor_user_id', null)
        ->assertJsonPath('data.recent_activity.0.actor_name', null);
});

it('returns an empty activity list for a log with nothing in it, rather than erroring', function () {
    // A fresh installation with nothing recorded yet is a legitimate state, and the Card must
    // render it as "nothing yet" rather than as a failure.
    actingAsRole(Role::SystemAdmin);

    adcCard()->assertOk()
        ->assertJsonPath('data.recent_activity', [])
        ->assertJsonPath('data.user_count', 1);
});

// ---------------------------------------------------------------------------
// Authorization — the Role is the gate, and no Permission substitutes for it
// ---------------------------------------------------------------------------

it('serves a System Administrator and a Super Administrator', function (Role $role) {
    actingAsRole($role);

    adcCard()->assertOk();
})->with([Role::SystemAdmin, Role::SuperAdmin]);

it('refuses every non-administrator role', function (Role $role) {
    // Asserted for the whole matrix, not a sample: this Card is the exception to the composition
    // rule, so a role leaking into it is a leak of the administration surface itself.
    actingAsRole($role);

    adcCard()->assertForbidden();
})->with([
    Role::Applicant,
    Role::Student,
    Role::Advisor,
    Role::Instructor,
    Role::StudentAffairs,
    Role::DepartmentHead,
    Role::Dean,
    Role::Council,
]);

it('refuses an unauthenticated request', function () {
    adcCard()->assertUnauthorized();
});
