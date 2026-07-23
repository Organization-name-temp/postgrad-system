<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\AcademicCalendar;
use Modules\Calendar\Public\Enums\Term;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Semester management — the academic term the university runs (ADR-0018, ticket 01),
| the single authority every later Phase-3 record reads from. Create/edit is gated on
| calendar.manage (Student Affairs / Dean; SysAdmin inherits); reading is open to any
| authenticated user. The five period windows are order-validated, (term, year) is
| unique, and period state (registration open, running, …) is derived from the dates,
| never stored. Semesters are edited in place, never hard-deleted. Written test-first
| against the /api/v1 HTTP seam (observable behaviour only).
|
| The base payload is a well-ordered calendar the ordering cases perturb one field at
| a time (the same reference the SemesterCalendar unit test uses).
*/

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function semesterPayload(array $overrides = []): array
{
    return array_merge([
        'term' => Term::Fall->value,
        'year' => 2026,
        'starts_at' => '2026-09-01T00:00:00+00:00',
        'ends_at' => '2026-12-20T00:00:00+00:00',
        'registration_opens_at' => '2026-08-01T00:00:00+00:00',
        'registration_closes_at' => '2026-09-05T00:00:00+00:00',
        'add_drop_ends_at' => '2026-09-14T00:00:00+00:00',
        'late_add_ends_at' => '2026-09-21T00:00:00+00:00',
        'withdrawal_deadline_at' => '2026-11-30T00:00:00+00:00',
    ], $overrides);
}

it('lets any authenticated user list semesters newest first', function () {
    Semester::factory()->create(['term' => Term::Fall, 'year' => 2025]);
    Semester::factory()->create(['term' => Term::Spring, 'year' => 2026]);
    actingAsRole(Role::Student); // no calendar.manage — reads are still allowed

    $this->getJson('/api/v1/semesters')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.year', 2026)
        ->assertJsonStructure([
            'data' => [[
                'id', 'term', 'year', 'name_en', 'name_ar',
                'starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at',
                'add_drop_ends_at', 'late_add_ends_at', 'withdrawal_deadline_at',
                'is_registration_open',
            ]],
        ]);
});

it('derives the bilingual name and the registration-open flag from the dates', function () {
    $semester = Semester::factory()->create([
        'term' => Term::Fall,
        'year' => 2026,
        'registration_opens_at' => now()->subWeek(),
        'registration_closes_at' => now()->addWeek(),
    ]);
    actingAsRole(Role::StudentAffairs);

    $this->getJson("/api/v1/semesters/{$semester->id}")
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Fall 2026')
        ->assertJsonPath('data.name_ar', 'خريف 2026')
        ->assertJsonPath('data.is_registration_open', true);
});

it('lets a calendar manager create a semester', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload())
        ->assertCreated()
        ->assertJsonPath('data.term', 'Fall')
        ->assertJsonPath('data.year', 2026)
        ->assertJsonPath('data.name_en', 'Fall 2026');

    $this->assertDatabaseHas('semesters', ['term' => 'Fall', 'year' => 2026]);
});

it('lets a Dean create a semester too (also holds calendar.manage)', function () {
    actingAsRole(Role::Dean);

    $this->postJson('/api/v1/semesters', semesterPayload(['term' => Term::Spring->value]))
        ->assertCreated();
});

it('reports registration open when the window contains the current moment', function () {
    actingAsRole(Role::StudentAffairs);

    $this->travelTo('2026-08-15', function () {
        $this->postJson('/api/v1/semesters', semesterPayload())
            ->assertCreated()
            ->assertJsonPath('data.is_registration_open', true);
    });

    $this->travelTo('2026-12-25', function () {
        $this->getJson('/api/v1/semesters')
            ->assertOk()
            ->assertJsonPath('data.0.is_registration_open', false);
    });
});

it('requires the term, year and all five period dates', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', ['name_en' => 'Nameless'])
        ->assertStatus(422)
        ->assertJsonValidationErrors([
            'term', 'year', 'starts_at', 'ends_at', 'registration_opens_at',
            'registration_closes_at', 'add_drop_ends_at', 'late_add_ends_at',
            'withdrawal_deadline_at',
        ]);
});

it('rejects a term outside the Fall/Spring set', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload(['term' => 'Summer']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('term');
});

it('rejects registration that opens after the semester starts', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload([
        'registration_opens_at' => '2026-09-02T00:00:00+00:00',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('registration_opens_at');
});

it('rejects registration that closes after the add/drop deadline', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload([
        'registration_closes_at' => '2026-09-20T00:00:00+00:00',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('registration_closes_at');
});

it('rejects add/drop ending after the late-add deadline', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload([
        'add_drop_ends_at' => '2026-09-25T00:00:00+00:00',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('add_drop_ends_at');
});

it('rejects late-add ending after the withdrawal deadline', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload([
        'late_add_ends_at' => '2026-12-01T00:00:00+00:00',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('late_add_ends_at');
});

it('rejects a withdrawal deadline after the semester ends', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload([
        'withdrawal_deadline_at' => '2026-12-25T00:00:00+00:00',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('withdrawal_deadline_at');
});

it('rejects a semester that ends before it starts', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload([
        'ends_at' => '2026-08-15T00:00:00+00:00',
        'withdrawal_deadline_at' => '2026-08-10T00:00:00+00:00',
        'late_add_ends_at' => '2026-08-05T00:00:00+00:00',
        'add_drop_ends_at' => '2026-08-03T00:00:00+00:00',
        'registration_closes_at' => '2026-08-02T00:00:00+00:00',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('ends_at');
});

it('rejects a duplicate term and year', function () {
    Semester::factory()->create(['term' => Term::Fall, 'year' => 2026]);
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload())
        ->assertStatus(422)
        ->assertJsonValidationErrors('term');
});

it('allows the same year under a different term and the same term in a different year', function () {
    Semester::factory()->create(['term' => Term::Fall, 'year' => 2026]);
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/semesters', semesterPayload(['term' => Term::Spring->value]))->assertCreated();
    $this->postJson('/api/v1/semesters', semesterPayload(['year' => 2027]))->assertCreated();
});

it('lets a manager edit a semester in place without tripping its own uniqueness', function () {
    $semester = Semester::factory()->create(['term' => Term::Fall, 'year' => 2026]);
    actingAsRole(Role::StudentAffairs);

    $this->patchJson("/api/v1/semesters/{$semester->id}", semesterPayload([
        'withdrawal_deadline_at' => '2026-11-15T00:00:00+00:00',
    ]))
        ->assertOk()
        ->assertJsonPath('data.withdrawal_deadline_at', '2026-11-15T00:00:00+00:00');

    $this->assertDatabaseHas('semesters', ['id' => $semester->id, 'year' => 2026]);
});

it('re-validates the ordering invariant on edit', function () {
    $semester = Semester::factory()->create(['term' => Term::Fall, 'year' => 2026]);
    actingAsRole(Role::StudentAffairs);

    $this->patchJson("/api/v1/semesters/{$semester->id}", semesterPayload([
        'withdrawal_deadline_at' => '2026-12-25T00:00:00+00:00',
    ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('withdrawal_deadline_at');
});

it('does not allow hard-deleting a semester', function () {
    $semester = Semester::factory()->create();
    actingAsRole(Role::StudentAffairs);

    $this->deleteJson("/api/v1/semesters/{$semester->id}")->assertStatus(405);
});

it('forbids creating or editing a semester without calendar.manage', function () {
    $semester = Semester::factory()->create();
    actingAsRole(Role::Student); // holds no calendar.manage

    $this->postJson('/api/v1/semesters', semesterPayload(['year' => 2030]))->assertForbidden();
    $this->patchJson("/api/v1/semesters/{$semester->id}", semesterPayload())->assertForbidden();
});

it('requires authentication to read or manage semesters', function () {
    $semester = Semester::factory()->create();

    $this->getJson('/api/v1/semesters')->assertUnauthorized();
    $this->getJson("/api/v1/semesters/{$semester->id}")->assertUnauthorized();
    $this->postJson('/api/v1/semesters', semesterPayload(['year' => 2031]))->assertUnauthorized();
});

/*
| The Calendar\Public read facade (ADR-0018) — the seam later Phase-3 tickets read
| the current/open Semester and its period answers through. Resolved from the
| container and exercised against real rows.
*/

it('resolves the registration-open semester through the read facade', function () {
    $open = Semester::factory()->registrationOpen()->create(['term' => Term::Fall, 'year' => 2026]);
    Semester::factory()->upcoming()->create(['term' => Term::Spring, 'year' => 2027]);
    Semester::factory()->ended()->create(['term' => Term::Fall, 'year' => 2024]);

    $summary = app(AcademicCalendar::class)->openForRegistration();

    expect($summary)->not->toBeNull()
        ->and($summary->id)->toBe($open->id)
        ->and($summary->term)->toBe(Term::Fall)
        ->and($summary->nameEn)->toBe('Fall 2026')
        ->and($summary->isRegistrationOpen(now()))->toBeTrue();
});

it('resolves the running semester and a semester by id through the read facade', function () {
    $running = Semester::factory()->running()->create(['term' => Term::Spring, 'year' => 2026]);

    $calendar = app(AcademicCalendar::class);

    expect($calendar->running()?->id)->toBe($running->id)
        ->and($calendar->running()?->isRunning(now()))->toBeTrue()
        ->and($calendar->find($running->id)?->nameEn)->toBe('Spring 2026')
        ->and($calendar->find(999999))->toBeNull();
});

it('returns null from the facade when no semester is registration-open', function () {
    Semester::factory()->ended()->create();
    Semester::factory()->upcoming()->create();

    expect(app(AcademicCalendar::class)->openForRegistration())->toBeNull();
});
