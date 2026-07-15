<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;

uses(RefreshDatabase::class);

/*
| Step-1 personal-info wizard (ticket 04) — the applicant fills the per-cycle
| snapshot with per-step "save & continue"/resume, then submits Step 1, which
| issues an on-screen + logged email-verification link. Written test-first against
| the /api/v1 HTTP seam: observable behaviour only (status codes, JSON bodies,
| persisted rows, the returned/logged link) — never the model or transition
| internals behind the module facades. Shared helpers (snapshotPayload,
| draftApplicant, userOf) live in tests/Pest.php.
*/

it('returns the current applicant’s draft application', function () {
    [$user, $application] = draftApplicant();
    Sanctum::actingAs($user);

    test()->getJson('/api/v1/application')
        ->assertOk()
        ->assertJsonPath('data.id', $application->id)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.name_en', null);
});

it('requires authentication to read an application', function () {
    test()->getJson('/api/v1/application')->assertUnauthorized();
});

it('saves a partial Step-1 snapshot and keeps the application a draft (save & resume)', function () {
    [$user, $application] = draftApplicant();
    Sanctum::actingAs($user);

    test()->patchJson('/api/v1/application', [
        'name_en' => 'Sara Applicant',
        'name_ar' => 'سارة المتقدمة',
        'nationality' => 'EG',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.name_en', 'Sara Applicant');

    // Resuming (a fresh read) returns the saved fields; the rest stay empty.
    test()->getJson('/api/v1/application')
        ->assertOk()
        ->assertJsonPath('data.name_ar', 'سارة المتقدمة')
        ->assertJsonPath('data.phone', null);

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'name_en' => 'Sara Applicant',
        'status' => 'draft',
    ]);
});

it('validates field formats on save, bilingually and inline', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    test()->patchJson('/api/v1/application', [
        'gender' => 'Other',
        'english_test' => 'MADE_UP',
        'date_of_birth' => 'not-a-date',
    ], ['Accept-Language' => 'ar'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['gender', 'english_test', 'date_of_birth']);
});

it('rejects a duplicate national id within the same cycle on save', function () {
    $cycle = AdmissionCycle::factory()->open()->create();
    Application::factory()->withPersonalInfo()->create([
        'admission_cycle_id' => $cycle->id,
        'national_id' => '29001010101010',
    ]);

    [$user] = draftApplicant($cycle);
    Sanctum::actingAs($user);

    test()->patchJson('/api/v1/application', ['national_id' => '29001010101010'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('national_id');
});

it('allows the same national id in a different cycle', function () {
    $otherCycle = AdmissionCycle::factory()->open()->create();
    Application::factory()->withPersonalInfo()->create([
        'admission_cycle_id' => $otherCycle->id,
        'national_id' => '29001010101010',
    ]);

    [$user] = draftApplicant(); // a different, open cycle
    Sanctum::actingAs($user);

    test()->patchJson('/api/v1/application', ['national_id' => '29001010101010'])
        ->assertOk();
});

it('keeps the snapshot editable through email verification, so back-navigation can fix a field pre-finalize', function () {
    // The documents-step state: submitted and email-verified, still in the applicant's
    // hands. Phase 2.5 B3 restores Back-to-personal-info from Documents, which re-saves
    // through this endpoint, so both states must accept an edit (not lock at submit).
    foreach (['submitted', 'email_verified'] as $status) {
        $application = Application::factory()->withPersonalInfo()->create(['status' => $status]);
        Sanctum::actingAs(userOf($application));

        test()->patchJson('/api/v1/application', ['name_en' => 'Corrected '.$status])
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Corrected '.$status);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'name_en' => 'Corrected '.$status,
        ]);
    }
});

it('blocks editing the snapshot once the application enters review', function () {
    $application = Application::factory()->underReview()->create();
    Sanctum::actingAs(userOf($application));

    test()->patchJson('/api/v1/application', ['name_en' => 'Changed'])
        ->assertStatus(409);
});

it('refuses to submit Step 1 until the snapshot is complete', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    test()->postJson('/api/v1/application/submit', ['name_en' => 'Sara Applicant'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name_ar', 'national_id', 'gender', 'previous_degree']);
});

it('rejects a National ID that is not exactly 14 digits', function () {
    // Phase 2.5 ticket 06: a National ID is `^\d{14}$` — 13 or 15 digits is a 422.
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    foreach (['2900101010101', '290010101010101'] as $bad) {
        test()->postJson('/api/v1/application/submit', snapshotPayload([
            'id_type' => 'national_id',
            'national_id' => $bad,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('national_id');
    }
});

it('validates a Passport identifier as 6–9 alphanumeric', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    // Too short (5) and too long (10) are both a 422 …
    foreach (['AB123', 'AB12345678'] as $bad) {
        test()->postJson('/api/v1/application/submit', snapshotPayload([
            'id_type' => 'passport',
            'national_id' => $bad,
            'nationality' => 'US',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('national_id');
    }

    // … an 8-character alphanumeric passport is accepted.
    test()->postJson('/api/v1/application/submit', snapshotPayload([
        'id_type' => 'passport',
        'national_id' => 'X1234567',
        'nationality' => 'US',
    ]))->assertOk();
});

it('rejects a phone that is not a canonical E.164 number', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    foreach (['01000000000', '+0123456789', 'not-a-phone'] as $bad) {
        test()->postJson('/api/v1/application/submit', snapshotPayload(['phone' => $bad]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }
});

it('rejects a nationality outside the ISO 3166-1 alpha-2 list', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    // The old free-text value ("Egyptian") is no longer accepted — it must be a code.
    test()->postJson('/api/v1/application/submit', snapshotPayload(['nationality' => 'Egyptian']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('nationality');
});

it('requires an english score unless the test is None', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    test()->postJson('/api/v1/application/submit', snapshotPayload([
        'english_test' => 'IELTS',
        'english_score' => null,
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('english_score');
});

it('accepts a None english test with no score', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    test()->postJson('/api/v1/application/submit', snapshotPayload([
        'english_test' => 'None',
        'english_score' => null,
    ]))
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');
});

it('submits Step 1, moving an unverified applicant to submitted and issuing a logged link', function () {
    [$user, $application] = draftApplicant();
    Sanctum::actingAs($user);

    Log::spy();

    $response = test()->postJson('/api/v1/application/submit', snapshotPayload())
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.name_en', 'Sara Applicant');

    // The verification link is returned on screen …
    $url = $response->json('verification_url');
    expect($url)->toContain('verify-email')->toContain('signature=');

    // … and written to the log (no mailer is bound).
    Log::shouldHaveReceived('info')->withArgs(fn (string $message) => str_contains($message, 'verify-email'))->atLeast()->once();

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'status' => 'submitted',
    ]);
    expect($application->fresh()->submitted_at)->not->toBeNull();
});
