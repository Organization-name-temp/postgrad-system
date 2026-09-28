<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/*
| Public self-registration — the first unauthenticated door into the system
| (ticket 03). Registering IS starting an application: one atomic act mints a User
| with the Applicant role (R01) and a Draft Application in an open Admission Cycle,
| then logs the user in via Sanctum. Written test-first against the /api/v1 HTTP
| seam — observable behaviour only (status codes, JSON, rows, the authenticated
| session), never the model/action internals behind the Public facades.
|
| The stateful `Origin` header marks these as first-party SPA requests so Sanctum
| establishes a session cookie (mirrors the login/logout feature tests).
*/

/** A complete, valid registration payload; overridable per case. */
function registerPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sara Applicant',
        'email' => 'sara@example.test',
        'password' => 'correct-horse',
        'password_confirmation' => 'correct-horse',
    ], $overrides);
}

/** POST the registration endpoint as a first-party (stateful) SPA request. */
function postRegister(array $payload): TestResponse
{
    return test()->postJson('/api/v1/register', $payload, ['Origin' => 'http://localhost']);
}

it('rejects stateless registration before creating a user or application', function () {
    AdmissionCycle::factory()->open()->create();
    $this->postJson('/api/v1/register', registerPayload())->assertStatus(400);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('applications', 0);
    $this->assertGuest();
});

it('registers an applicant into the single open cycle and logs them in', function () {
    $cycle = AdmissionCycle::factory()->open()->create();

    postRegister(registerPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.admission_cycle_id', $cycle->id);

    // One User, holding R01, owning one Draft Application tied to the open cycle.
    $user = User::query()->where('email', 'sara@example.test')->firstOrFail();
    expect($user->roles()->pluck('value'))->toContain('R01');

    $this->assertDatabaseHas('applications', [
        'user_id' => $user->id,
        'admission_cycle_id' => $cycle->id,
        'status' => 'draft',
    ]);

    // The password is stored hashed, never in the clear.
    expect($user->password)->not->toBe('correct-horse');

    // Registering logged them in: the session now resolves them at /me as R01.
    $this->assertAuthenticated();
    $this->getJson('/api/v1/me', ['Origin' => 'http://localhost'])
        ->assertOk()
        ->assertJsonPath('data.email', 'sara@example.test')
        ->assertJsonPath('data.roles.0.code', 'R01');
});

it('refuses registration with an admissions-closed message when no cycle is open', function () {
    AdmissionCycle::factory()->closed()->create();
    AdmissionCycle::factory()->past()->create();
    AdmissionCycle::factory()->future()->create();

    postRegister(registerPayload())
        ->assertStatus(409)
        ->assertJsonPath('message', 'Admissions are currently closed.');

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('applications', 0);
    $this->assertGuest();
});

it('auto-assigns the only open cycle, ignoring closed ones', function () {
    $open = AdmissionCycle::factory()->open()->create();
    AdmissionCycle::factory()->past()->create();
    AdmissionCycle::factory()->closed()->create();

    postRegister(registerPayload())
        ->assertCreated()
        ->assertJsonPath('data.admission_cycle_id', $open->id);
});

it('requires choosing a cycle when more than one is open', function () {
    AdmissionCycle::factory()->open()->create();
    AdmissionCycle::factory()->open()->create();

    postRegister(registerPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('admission_cycle_id');

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('applications', 0);
});

it('files the application against the chosen cycle when several are open', function () {
    $first = AdmissionCycle::factory()->open()->create();
    $second = AdmissionCycle::factory()->open()->create();

    postRegister(registerPayload(['admission_cycle_id' => $second->id]))
        ->assertCreated()
        ->assertJsonPath('data.admission_cycle_id', $second->id);

    expect($first->applications()->count())->toBe(0);
    expect($second->applications()->count())->toBe(1);
});

it('rejects a chosen cycle that is not open', function () {
    AdmissionCycle::factory()->open()->create();
    AdmissionCycle::factory()->open()->create();
    $closed = AdmissionCycle::factory()->past()->create();

    postRegister(registerPayload(['admission_cycle_id' => $closed->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('admission_cycle_id');

    $this->assertDatabaseCount('users', 0);
});

it('rejects a duplicate email', function () {
    AdmissionCycle::factory()->open()->create();
    User::factory()->create(['email' => 'sara@example.test']);

    postRegister(registerPayload(['email' => 'sara@example.test']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    // No second user, and no orphan application from the failed attempt.
    expect(User::query()->where('email', 'sara@example.test')->count())->toBe(1);
    $this->assertDatabaseCount('applications', 0);
});

it('requires a name, email, and a confirmed password', function () {
    AdmissionCycle::factory()->open()->create();

    postRegister([])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('rejects a password that fails confirmation', function () {
    AdmissionCycle::factory()->open()->create();

    postRegister(registerPayload(['password_confirmation' => 'does-not-match']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('password');
});

it('rate-limits registration attempts from one client', function () {
    AdmissionCycle::factory()->open()->create();

    // The throttle counts every request (it runs before validation), so five
    // empty-body attempts exhaust the window; the sixth is turned away with 429.
    for ($i = 0; $i < 5; $i++) {
        postRegister([])->assertStatus(422);
    }

    postRegister(registerPayload())->assertStatus(429);
});
