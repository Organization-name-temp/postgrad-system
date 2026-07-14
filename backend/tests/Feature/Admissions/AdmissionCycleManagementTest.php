<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Enums\IntakeTerm;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Admission Cycle management — the windowed intake object every Application hangs
| off. CRUD + activate/deactivate is gated on calendar.manage (Student Affairs /
| Dean); "open" is derived, never stored (ADR-0012, ROADMAP §Phase 2, ticket 01).
| Written test-first against the /api/v1 HTTP seam (observable behaviour only).
*/

it('lets a calendar manager list cycles', function () {
    AdmissionCycle::factory()->count(2)->create();
    actingAsRole(Role::StudentAffairs); // holds calendar.manage

    $this->getJson('/api/v1/admission-cycles')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure([
            'data' => [[
                'id', 'intake_term', 'year', 'name_en', 'name_ar',
                'opens_at', 'closes_at', 'is_active', 'is_open',
            ]],
        ]);
});

it('lets a manager read a single cycle with both language names and derived open state', function () {
    $cycle = AdmissionCycle::factory()->open()->create([
        'name_en' => 'Fall 2026',
        'name_ar' => 'خريف ٢٠٢٦',
    ]);
    actingAsRole(Role::Dean); // also holds calendar.manage

    $this->getJson("/api/v1/admission-cycles/{$cycle->id}")
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Fall 2026')
        ->assertJsonPath('data.name_ar', 'خريف ٢٠٢٦')
        ->assertJsonPath('data.is_open', true);
});

it('lets a manager create a cycle, active and open by default within its window', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2026,
        'name_en' => 'Fall 2026',
        'name_ar' => 'خريف ٢٠٢٦',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.intake_term', 'Fall')
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_open', true);

    $this->assertDatabaseHas('admission_cycles', [
        'intake_term' => 'Fall',
        'year' => 2026,
        'is_active' => true,
    ]);
});

it('requires both language names', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2026,
        'name_en' => 'Fall 2026',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name_ar');
});

it('requires intake term, year and a window', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/admission-cycles', [
        'name_en' => 'Nameless',
        'name_ar' => 'بلا اسم',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['intake_term', 'year', 'opens_at', 'closes_at']);
});

it('rejects an intake term outside the Fall/Spring set', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => 'Summer',
        'year' => 2026,
        'name_en' => 'Summer 2026',
        'name_ar' => 'صيف ٢٠٢٦',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('intake_term');
});

it('rejects a close date that is not after the open date', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2026,
        'name_en' => 'Fall 2026',
        'name_ar' => 'خريف ٢٠٢٦',
        'opens_at' => now()->addMonth()->toIso8601String(),
        'closes_at' => now()->subDay()->toIso8601String(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('closes_at');
});

it('rejects a duplicate intake term and year', function () {
    AdmissionCycle::factory()->create(['intake_term' => IntakeTerm::Fall, 'year' => 2026]);
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2026,
        'name_en' => 'Duplicate',
        'name_ar' => 'مكرر',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('intake_term');
});

it('allows the same year under a different term and the same term in a different year', function () {
    AdmissionCycle::factory()->create(['intake_term' => IntakeTerm::Fall, 'year' => 2026]);
    actingAsRole(Role::StudentAffairs);

    // Same year, different term.
    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => IntakeTerm::Spring->value,
        'year' => 2026,
        'name_en' => 'Spring 2026',
        'name_ar' => 'ربيع ٢٠٢٦',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])->assertCreated();

    // Same term, different year.
    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2027,
        'name_en' => 'Fall 2027',
        'name_ar' => 'خريف ٢٠٢٧',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])->assertCreated();
});

it('lets a manager edit a cycle without tripping its own uniqueness', function () {
    $cycle = AdmissionCycle::factory()->create([
        'intake_term' => IntakeTerm::Fall,
        'year' => 2026,
        'name_en' => 'Old name',
    ]);
    actingAsRole(Role::StudentAffairs);

    $this->patchJson("/api/v1/admission-cycles/{$cycle->id}", [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2026,
        'name_en' => 'Fall 2026 intake',
        'name_ar' => 'دفعة خريف ٢٠٢٦',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Fall 2026 intake');

    $this->assertDatabaseHas('admission_cycles', ['id' => $cycle->id, 'name_en' => 'Fall 2026 intake']);
});

it('deactivating a cycle closes it even inside its window; reactivating reopens it', function () {
    $cycle = AdmissionCycle::factory()->open()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/admission-cycles/{$cycle->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.is_open', false);
    $this->assertDatabaseHas('admission_cycles', ['id' => $cycle->id, 'is_active' => false]);

    $this->postJson("/api/v1/admission-cycles/{$cycle->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.is_open', true);
});

it('forbids all management actions for a user without calendar.manage', function () {
    $cycle = AdmissionCycle::factory()->create();
    actingAsRole(Role::Applicant); // no calendar.manage

    $this->getJson('/api/v1/admission-cycles')->assertForbidden();
    $this->postJson('/api/v1/admission-cycles', [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2030,
        'name_en' => 'x',
        'name_ar' => 'س',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])->assertForbidden();
    $this->patchJson("/api/v1/admission-cycles/{$cycle->id}", [
        'intake_term' => IntakeTerm::Fall->value,
        'year' => 2030,
        'name_en' => 'x',
        'name_ar' => 'س',
        'opens_at' => now()->subDay()->toIso8601String(),
        'closes_at' => now()->addMonth()->toIso8601String(),
    ])->assertForbidden();
    $this->postJson("/api/v1/admission-cycles/{$cycle->id}/deactivate")->assertForbidden();
    $this->postJson("/api/v1/admission-cycles/{$cycle->id}/activate")->assertForbidden();
});

it('requires authentication to manage cycles', function () {
    $this->getJson('/api/v1/admission-cycles')->assertUnauthorized();
});

it('does not allow hard-deleting a cycle', function () {
    $cycle = AdmissionCycle::factory()->create();
    actingAsRole(Role::StudentAffairs);

    $this->deleteJson("/api/v1/admission-cycles/{$cycle->id}")->assertStatus(405);
});

it('exposes currently-open cycles publicly with no authentication', function () {
    $open = AdmissionCycle::factory()->open()->create(['name_en' => 'Fall 2026']);
    AdmissionCycle::factory()->closed()->create();   // inactive
    AdmissionCycle::factory()->past()->create();      // window elapsed
    AdmissionCycle::factory()->future()->create();    // window not yet started

    $this->getJson('/api/v1/admission-cycles/open')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->id)
        ->assertJsonPath('data.0.name_en', 'Fall 2026')
        ->assertJsonPath('data.0.is_open', true);
});

it('returns an empty open-cycle list when nothing is open', function () {
    AdmissionCycle::factory()->closed()->create();
    AdmissionCycle::factory()->past()->create();

    $this->getJson('/api/v1/admission-cycles/open')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
