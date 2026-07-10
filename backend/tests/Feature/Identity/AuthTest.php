<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

it('logs in with valid credentials and records a login audit entry', function () {
    $user = User::factory()->create([
        'email' => 'r05@ejust.test',
        'password' => 'password',
    ]);
    $user->assignRole(Role::StudentAffairs);

    postJson('/api/v1/login',
        ['email' => 'r05@ejust.test', 'password' => 'password'],
        ['Origin' => 'http://localhost'],
    )
        ->assertOk()
        ->assertJsonPath('data.email', 'r05@ejust.test')
        ->assertJsonPath('data.roles.0.code', 'R05');

    $this->assertDatabaseHas('audit_log', [
        'action' => 'login',
        'actor_user_id' => $user->id,
    ]);
});

it('rejects invalid credentials and records a failed-login audit entry', function () {
    User::factory()->create([
        'email' => 'r05@ejust.test',
        'password' => 'password',
    ]);

    postJson('/api/v1/login',
        ['email' => 'r05@ejust.test', 'password' => 'wrong-password'],
        ['Origin' => 'http://localhost'],
    )->assertStatus(422);

    $this->assertDatabaseHas('audit_log', ['action' => 'login_failed']);
});

it('returns the authenticated user and their roles from /me', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Advisor);
    Sanctum::actingAs($user);

    getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles.0.code', 'R03');
});

it('rejects /me when unauthenticated', function () {
    getJson('/api/v1/me')->assertUnauthorized();
});

it('logs out an authenticated session and records a logout audit entry', function () {
    $user = User::factory()->create([
        'email' => 'r05@ejust.test',
        'password' => 'password',
    ]);

    postJson('/api/v1/login',
        ['email' => 'r05@ejust.test', 'password' => 'password'],
        ['Origin' => 'http://localhost'],
    )->assertOk();

    postJson('/api/v1/logout', [], ['Origin' => 'http://localhost'])
        ->assertNoContent();

    $this->assertDatabaseHas('audit_log', [
        'action' => 'logout',
        'actor_user_id' => $user->id,
    ]);
});
