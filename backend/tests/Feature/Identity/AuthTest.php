<?php

use Illuminate\Auth\Events\Attempting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('logs in with valid credentials and records a login audit entry', function () {
    $user = User::factory()->create([
        'email' => 'r05@ejust.test',
        'password' => 'password',
    ]);
    $user->assignRole(Role::StudentAffairs);

    $this->postJson('/api/v1/login',
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

    $this->postJson('/api/v1/login',
        ['email' => 'r05@ejust.test', 'password' => 'wrong-password'],
        ['Origin' => 'http://localhost'],
    )->assertStatus(422);

    $this->assertDatabaseHas('audit_log', ['action' => 'login_failed']);
});

it('returns the authenticated user and their roles from /me', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Advisor);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.roles.0.code', 'R03');
});

it('rejects /me when unauthenticated', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('logs out an authenticated session and records a logout audit entry', function () {
    $user = User::factory()->create([
        'email' => 'r05@ejust.test',
        'password' => 'password',
    ]);

    $this->postJson('/api/v1/login',
        ['email' => 'r05@ejust.test', 'password' => 'password'],
        ['Origin' => 'http://localhost'],
    )->assertOk();

    $this->postJson('/api/v1/logout', [], ['Origin' => 'http://localhost'])
        ->assertNoContent();

    $this->assertDatabaseHas('audit_log', [
        'action' => 'logout',
        'actor_user_id' => $user->id,
    ]);
});

it('rejects stateless login before authentication and audit side effects', function () {
    User::factory()->create(['email' => 'stateless@example.test', 'password' => 'password']);
    $this->postJson('/api/v1/login', ['email' => 'stateless@example.test', 'password' => 'password'])->assertStatus(400);
    $this->assertGuest();
    $this->assertDatabaseCount('audit_log', 0);
});

it('blocks the sixth failed account attempt across email case and client IPs and permits retry after expiry', function () {
    $this->freezeTime();
    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($i + 1)])
            ->postJson('/api/v1/login', ['email' => 'guess@example.test', 'password' => 'wrong'], ['Origin' => 'http://localhost'])
            ->assertUnprocessable();
    }
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.9'])
        ->postJson('/api/v1/login', ['email' => 'GUESS@example.test', 'password' => 'wrong'], ['Origin' => 'http://localhost'])
        ->assertStatus(429)->assertHeader('Retry-After', '60');
    expect(AuditLog::query()->where('action', 'login_failed')->count())->toBe(5);
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/login', ['email' => 'guess@example.test', 'password' => 'wrong'], ['Origin' => 'http://localhost'])->assertUnprocessable();
});

it('limits rotating account guesses from one IP and localizes the rejection', function () {
    $this->freezeTime();
    config(['auth.login_throttle.ip_max_attempts' => 2]);
    foreach (['first', 'second'] as $email) {
        $this->postJson('/api/v1/login', ['email' => $email.'@example.test', 'password' => 'wrong'], ['Origin' => 'http://localhost'])->assertUnprocessable();
    }
    $this->postJson('/api/v1/login', ['email' => 'third@example.test', 'password' => 'wrong'], ['Origin' => 'http://localhost', 'Accept-Language' => 'ar'])
        ->assertStatus(429)->assertJsonPath('message', 'عدد كبير جدًا من محاولات تسجيل الدخول. يرجى المحاولة مرة أخرى بعد 60 ثانية.');
    expect(AuditLog::query()->where('action', 'login_failed')->count())->toBe(2);
});

it('successful login resets only its account failures and does not consume the failure budget', function () {
    $user = User::factory()->create(['email' => 'recover@example.test', 'password' => 'password']);
    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'], ['Origin' => 'http://localhost'])->assertUnprocessable();
    }
    for ($i = 0; $i < 6; $i++) {
        $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'], ['Origin' => 'http://localhost'])->assertOk();
    }
    config(['auth.login_throttle.ip_max_attempts' => 5]);
    $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'wrong'], ['Origin' => 'http://localhost'])->assertUnprocessable();
    $this->postJson('/api/v1/login', ['email' => 'another@example.test', 'password' => 'wrong'], ['Origin' => 'http://localhost'])->assertStatus(429);
});

it('rejects a competing login before password checks while an overlapping budget is locked', function (string $budget) {
    $email = 'competing@example.test';
    $key = $budget === 'account'
        ? 'login-account:'.hash('sha256', $email)
        : 'login-ip:'.hash('sha256', '127.0.0.1');
    $lock = Cache::lock($key.':verification', 30);
    expect($lock->get())->toBeTrue();
    Event::fake([Attempting::class]);
    try {
        $this->postJson('/api/v1/login', ['email' => strtoupper($email), 'password' => 'wrong'], ['Origin' => 'http://localhost'])
            ->assertStatus(429)->assertHeader('Retry-After', '1');
        $this->assertDatabaseCount('audit_log', 0);
        Event::assertNotDispatched(Attempting::class);
    } finally {
        $lock->release();
    }
})->with(['account', 'ip']);
