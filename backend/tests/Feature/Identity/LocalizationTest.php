<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

it('persists the preferred locale and returns it on the next /me', function () {
    $user = User::factory()->create(['preferred_locale' => 'en']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['preferred_locale' => 'ar'])
        ->assertOk()
        ->assertJsonPath('data.preferred_locale', 'ar');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'preferred_locale' => 'ar',
    ]);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.preferred_locale', 'ar');
});

it('rejects an unsupported preferred locale', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['preferred_locale' => 'fr'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('preferred_locale');
});

it('returns the login error in English by default', function () {
    User::factory()->create(['email' => 'r05@ejust.test', 'password' => 'password']);

    $this->postJson('/api/v1/login',
        ['email' => 'r05@ejust.test', 'password' => 'wrong-password'],
        ['Origin' => 'http://localhost', 'Accept-Language' => 'en'],
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
});

it('negotiates the login error into Arabic from Accept-Language', function () {
    User::factory()->create(['email' => 'r05@ejust.test', 'password' => 'password']);

    $this->postJson('/api/v1/login',
        ['email' => 'r05@ejust.test', 'password' => 'wrong-password'],
        ['Origin' => 'http://localhost', 'Accept-Language' => 'ar'],
    )
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'بيانات الاعتماد هذه غير متطابقة مع سجلاتنا.');
});
