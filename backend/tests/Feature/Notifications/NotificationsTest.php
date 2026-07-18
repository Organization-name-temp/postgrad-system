<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\User;
use Modules\Notifications\Public\Contracts\Notifier;
use Modules\Notifications\Public\Enums\NotificationType;

uses(RefreshDatabase::class);

/*
| Notifications — the cross-cutting in-app messaging that fills the shell's bell
| slot (ticket 02). Any module enqueues a notification for a User through the
| Notifier Public contract; the User reads their own via /api/v1/notifications,
| sees an unread count, and marks read. Content is a type + payload (no
| pre-rendered text — the SPA renders bilingually from the type). Written
| test-first against the HTTP seam; observable behaviour only, and every
| endpoint is scoped to the authenticated user (a user never touches another's).
*/

/** Enqueue a notification through the Public contract (as any module would). */
function notify(User $user, NotificationType $type = NotificationType::InterviewScheduled, array $payload = []): void
{
    app(Notifier::class)->notify($user->getKey(), $type, $payload);
}

it('creates a notification row when a module notifies a user', function () {
    $user = User::factory()->create();

    notify($user, NotificationType::InterviewScheduled, ['scheduled_at' => '2026-09-01T10:00:00+00:00']);

    $this->assertDatabaseHas('notifications', [
        'user_id' => $user->getKey(),
        'type' => 'interview.scheduled',
        'read_at' => null,
    ]);

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'interview.scheduled')
        ->assertJsonPath('data.0.data.scheduled_at', '2026-09-01T10:00:00+00:00')
        ->assertJsonPath('data.0.read_at', null);
});

it('lists a user\'s notifications newest-first with the expected shape', function () {
    $user = User::factory()->create();
    notify($user, NotificationType::ApplicationReturned);
    notify($user, NotificationType::DecisionRecorded);
    notify($user, NotificationType::ApplicationAccepted);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        // Newest first: the acceptance was enqueued last.
        ->assertJsonPath('data.0.type', 'application.accepted')
        ->assertJsonPath('data.2.type', 'application.returned')
        ->assertJsonStructure([
            'data' => [['id', 'type', 'data', 'read_at', 'created_at']],
            'meta' => ['current_page', 'per_page', 'total'],
        ]);
});

it('reports the unread count, ignoring read notifications', function () {
    $user = User::factory()->create();
    notify($user);
    notify($user);
    notify($user);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 3);
});

it('marks a single notification read, dropping the unread count', function () {
    $user = User::factory()->create();
    notify($user);
    notify($user);
    Sanctum::actingAs($user);

    $id = $this->getJson('/api/v1/notifications')->json('data.0.id');

    $this->postJson("/api/v1/notifications/{$id}/read")->assertNoContent();

    // The marked row is no longer unread, and the count drops from 2 to 1.
    $this->assertDatabaseMissing('notifications', ['id' => $id, 'read_at' => null]);
    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 1);
});

it('marks all of a user\'s notifications read at once', function () {
    $user = User::factory()->create();
    notify($user);
    notify($user);
    notify($user);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/notifications/read-all')->assertNoContent();

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.count', 0);
});

it('never lists another user\'s notifications', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    notify($other);
    notify($other);
    notify($me);
    Sanctum::actingAs($me);

    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/notifications/unread-count')
        ->assertJsonPath('data.count', 1);
});

it('refuses to mark another user\'s notification read (404, and it stays unread)', function () {
    $owner = User::factory()->create();
    notify($owner);
    Sanctum::actingAs($owner);
    $id = $this->getJson('/api/v1/notifications')->json('data.0.id');

    $attacker = User::factory()->create();
    Sanctum::actingAs($attacker);
    $this->postJson("/api/v1/notifications/{$id}/read")->assertNotFound();

    $this->assertDatabaseHas('notifications', ['id' => $id, 'read_at' => null]);
});

it('marking all read only touches the acting user\'s notifications', function () {
    $other = User::factory()->create();
    notify($other);
    $me = User::factory()->create();
    notify($me);
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/notifications/read-all')->assertNoContent();

    // The other user's notification is untouched.
    Sanctum::actingAs($other);
    $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.count', 1);
});

it('requires authentication for every notifications endpoint', function () {
    $this->getJson('/api/v1/notifications')->assertUnauthorized();
    $this->getJson('/api/v1/notifications/unread-count')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/1/read')->assertUnauthorized();
    $this->postJson('/api/v1/notifications/read-all')->assertUnauthorized();
});
