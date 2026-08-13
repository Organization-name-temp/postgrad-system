<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Calendar\Internal\Models\Semester;
use Modules\Calendar\Public\Enums\Term;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Semester & Open Windows Card (phase 4.5, ticket 06) — the first of the two configuration
| Cards: which Semester is live, which of its windows are open right now, and the next date due.
|
| Window openness is ADR-0018's derived period state, already covered at the arithmetic seam by
| the SemesterCalendar unit test — what is asserted here is that the Card *reads* that
| derivation (never a stored flag), which Semester it picks out of a world running several at
| once, and who is allowed to read it. Self-contained helper (swc* prefix) so a single-file run
| works.
*/

function swcCard()
{
    return test()->getJson('/api/v1/calendar/cards/semester-windows');
}

// ---------------------------------------------------------------------------
// Which Semester is live, and what is open on it
// ---------------------------------------------------------------------------

it('names the running semester, the windows open on it, and the next date due', function () {
    // A world running three Semesters at different points in their lifecycle at once — the
    // seeded shape, and the reason "which one is live" is a real question.
    Semester::factory()->ended()->create(['term' => Term::Spring, 'year' => 2025]);
    $running = Semester::factory()->running()->create(['term' => Term::Fall, 'year' => 2026]);
    Semester::factory()->upcoming()->create(['term' => Term::Spring, 'year' => 2027]);

    actingAsRole(Role::StudentAffairs); // holds calendar.manage (§3.2)

    swcCard()->assertOk()
        // The Semester travels as domain data — id, term, year and both names — never as a
        // pre-rendered label, so the SPA renders it bilingually (the Notifications rule).
        ->assertJsonPath('data.semester.id', $running->id)
        ->assertJsonPath('data.semester.term', $running->term->value)
        ->assertJsonPath('data.semester.year', 2026)
        ->assertJsonPath('data.semester.name_en', $running->name_en)
        ->assertJsonPath('data.semester.name_ar', $running->name_ar)
        // The `running` factory state sits past late-add and before the withdrawal deadline.
        ->assertJsonPath('data.current_period', 'withdrawal')
        ->assertJsonPath('data.open_windows', ['withdrawal'])
        ->assertJsonPath('data.next_deadline.window', 'withdrawal')
        ->assertJsonPath(
            'data.next_deadline.at',
            $running->withdrawal_deadline_at->toIso8601String(),
        );
});

it('lists every window open at once, not just the phase the semester reads as', function () {
    // Registration may still be open past the semester start, so the two windows overlap:
    // `current_period` names the single live phase, `open_windows` says what is actually open.
    $semester = Semester::factory()->create([
        'term' => Term::Fall,
        'year' => 2026,
        'registration_opens_at' => now()->subWeeks(4),
        'registration_closes_at' => now()->addWeek(),
        'starts_at' => now()->subWeek(),
        'add_drop_ends_at' => now()->addWeeks(2),
        'late_add_ends_at' => now()->addWeeks(3),
        'withdrawal_deadline_at' => now()->addWeeks(8),
        'ends_at' => now()->addWeeks(14),
        'grade_submission_deadline_at' => now()->addWeeks(16),
    ]);

    actingAsRole(Role::StudentAffairs);

    swcCard()->assertOk()
        ->assertJsonPath('data.semester.id', $semester->id)
        // The change periods take precedence where they overlap the registration window.
        ->assertJsonPath('data.current_period', 'add_drop')
        ->assertJsonPath('data.open_windows', ['registration', 'add_drop'])
        // The registration close is nearer than the add/drop end.
        ->assertJsonPath('data.next_deadline.window', 'registration');
});

it('falls back to the semester open for registration when no term is running', function () {
    // Nothing is running, so the Card is about the term being configured — the one whose
    // registration window is open.
    Semester::factory()->ended()->create(['term' => Term::Spring, 'year' => 2025]);
    $opening = Semester::factory()->registrationOpen()->create(['term' => Term::Fall, 'year' => 2026]);

    actingAsRole(Role::StudentAffairs);

    swcCard()->assertOk()
        ->assertJsonPath('data.semester.id', $opening->id)
        ->assertJsonPath('data.current_period', 'registration')
        ->assertJsonPath('data.open_windows', ['registration']);
});

// ---------------------------------------------------------------------------
// The empty case — a meaningful state, never an error
// ---------------------------------------------------------------------------

it('returns a null semester when no term is live at all, rather than erroring', function () {
    Semester::factory()->ended()->create(['term' => Term::Spring, 'year' => 2025]);
    Semester::factory()->upcoming()->create(['term' => Term::Fall, 'year' => 2027]);

    actingAsRole(Role::StudentAffairs);

    swcCard()->assertOk()
        ->assertJsonPath('data.semester', null)
        ->assertJsonPath('data.current_period', null)
        ->assertJsonPath('data.open_windows', [])
        ->assertJsonPath('data.next_deadline', null);
});

it('returns a null semester for an empty calendar', function () {
    actingAsRole(Role::StudentAffairs);

    swcCard()->assertOk()->assertJsonPath('data.semester', null);
});

// ---------------------------------------------------------------------------
// Authorization — the server decides, not the client's choice of what to request
// ---------------------------------------------------------------------------

it('refuses a user who does not hold calendar.manage', function () {
    Semester::factory()->running()->create();
    actingAsRole(Role::Student);

    swcCard()->assertForbidden();
});

it('serves an administrator, who inherits calendar.manage', function () {
    // The configuration Cards are members of the administration Card set (ticket 07), so an
    // administrator must actually be able to read them.
    Semester::factory()->running()->create();
    actingAsRole(Role::SystemAdmin);

    swcCard()->assertOk();
});

it('refuses an unauthenticated request', function () {
    swcCard()->assertUnauthorized();
});
