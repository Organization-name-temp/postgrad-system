<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/*
| Email verification (ticket 04) — after submitting Step 1 the applicant receives
| a signed, 24h-expiring link (returned on screen + logged, no mailer). Clicking
| it advances Submitted → EmailVerified and stamps users.email_verified_at;
| resending is throttled to 5/24h; an already-verified email shortcuts the whole
| step. Driven through the /api/v1 HTTP seam and the signed link itself. Shared
| helpers (snapshotPayload, draftApplicant, userOf) live in tests/Pest.php.
*/

/** Submit Step 1 for a fresh unverified applicant and return [User, Application, verificationUrl]. */
function submitStepOne(): array
{
    [$user, $application] = draftApplicant();
    Sanctum::actingAs($user);
    $url = test()->postJson('/api/v1/application/submit', snapshotPayload())
        ->assertOk()
        ->json('verification_url');

    return [$user, $application, $url];
}

it('verifies the email via the signed link, advancing submitted → email_verified', function () {
    [$user, $application, $url] = submitStepOne();

    test()->getJson($url)
        ->assertOk()
        ->assertJsonPath('data.status', 'email_verified');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
    expect($application->fresh()->status->value)->toBe('email_verified');
});

it('rejects an unsigned or tampered verification link with 403', function () {
    [, $application] = submitStepOne();

    // The route without a valid signature is refused.
    test()->getJson(route('admissions.verify-email', ['application' => $application->id]))
        ->assertForbidden();

    expect($application->fresh()->status->value)->toBe('submitted');
});

it('treats a re-clicked verification link as idempotent', function () {
    [$user, , $url] = submitStepOne();

    test()->getJson($url)->assertOk();
    test()->getJson($url)->assertOk()->assertJsonPath('data.status', 'email_verified');

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

it('resends a fresh verification link and logs it', function () {
    [$user] = submitStepOne();
    Sanctum::actingAs($user);

    Log::spy();

    $url = test()->postJson('/api/v1/application/resend-verification')
        ->assertOk()
        ->json('verification_url');

    expect($url)->toContain('verify-email')->toContain('signature=');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'verify-email'))->atLeast()->once();
});

it('throttles verification resends to five per day', function () {
    [$user] = submitStepOne();
    Sanctum::actingAs($user);

    for ($i = 0; $i < 5; $i++) {
        test()->postJson('/api/v1/application/resend-verification')->assertOk();
    }

    test()->postJson('/api/v1/application/resend-verification')->assertStatus(429);
});

it('refuses to resend when the application is not awaiting verification', function () {
    [$user] = draftApplicant(); // still a Draft — Step 1 not submitted
    Sanctum::actingAs($user);

    test()->postJson('/api/v1/application/resend-verification')->assertStatus(409);
});

it('shortcuts a pre-verified applicant straight to email_verified with no link', function () {
    $cycle = AdmissionCycle::factory()->open()->create();
    $user = User::factory()->create(); // verified by default
    $application = Application::factory()->create([
        'user_id' => $user->id,
        'admission_cycle_id' => $cycle->id,
    ]);
    Sanctum::actingAs($user);

    test()->postJson('/api/v1/application/submit', snapshotPayload())
        ->assertOk()
        ->assertJsonPath('data.status', 'email_verified')
        ->assertJsonPath('verification_url', null);

    expect($application->fresh()->submitted_at)->not->toBeNull();
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
