<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The two Admissions Dashboard Cards (phase 4.5, ticket 03) — one for each side of the admissions
| counter: My Application (the Applicant's own status) and the Screening Queue (Student Affairs'
| waiting work, which closes SRS FR-ADM-005.5).
|
| The aging arithmetic itself is covered at its own seam by OverdueFlaggingCommandTest, so what is
| asserted here is that the Card *reads* that same derivation — business days with the Egyptian
| Fri/Sat weekend excluded, against the configured SLA — that overdue changes and blocks nothing,
| and who is allowed to read each Card.
|
| Time is frozen so ages are deterministic, exactly as the command's test does it: an entry placed
| 14 calendar days back is always 10 business days old (two whole weeks = two Fri + two Sat
| removed), safely past the default 5-day SLA; one placed a day back is within it. Self-contained
| helpers (ac* prefix) so a single-file run works.
*/

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
});

/** An Applicant (R01) with no Application of their own yet. */
function acApplicant(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Applicant);

    return $user;
}

/**
 * An Application owned by `$user` resting in `$status`, with the single timeline row recording that
 * it entered that status `$daysAgo` calendar days back — which is what the derived age is measured
 * from, since aging is never stored.
 */
function acApplication(User $user, ApplicationStatus $status, int $daysAgo, string $nameEn = 'Sara Applicant'): Application
{
    $application = Application::factory()->underReview()->create([
        'user_id' => $user->id,
        'status' => $status,
        'name_en' => $nameEn,
        'finalized_at' => now()->subDays($daysAgo),
    ]);

    ApplicationStatusTransition::factory()
        ->enteredAt($status, now()->subDays($daysAgo))
        ->create(['application_id' => $application->id]);

    return $application;
}

function acMyApplicationCard()
{
    return test()->getJson('/api/v1/admissions/cards/my-application');
}

function acScreeningCard()
{
    return test()->getJson('/api/v1/admissions/cards/screening-queue');
}

// ---------------------------------------------------------------------------
// My Application — where the acting applicant stands
// ---------------------------------------------------------------------------

it('returns the acting applicant’s current status as a typed value, with when it was entered', function () {
    $applicant = acApplicant();
    $application = acApplication($applicant, ApplicationStatus::UnderSAReview, daysAgo: 3);

    Sanctum::actingAs($applicant);

    acMyApplicationCard()->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta', [])
        ->assertJsonPath('data.id', (int) $application->getKey())
        // The status travels as its enum value, never as a sentence: the SPA renders the chip and
        // picks the "what happens next" copy from it.
        ->assertJsonPath('data.status', ApplicationStatus::UnderSAReview->value)
        ->assertJsonPath('data.status_entered_at', now()->subDays(3)->toIso8601String());
});

it('returns the most recent application when an applicant has re-applied in a later cycle', function () {
    $applicant = acApplicant();
    acApplication($applicant, ApplicationStatus::Rejected, daysAgo: 90);
    $current = acApplication($applicant, ApplicationStatus::UnderSAReview, daysAgo: 2);

    Sanctum::actingAs($applicant);

    acMyApplicationCard()->assertOk()->assertJsonPath('data.id', (int) $current->getKey());
});

it('returns a null payload for an applicant who has not started an application', function () {
    // Possible between account creation and the first draft: a legitimate state, not an error, so
    // the Card renders its empty state rather than showing a failure.
    Sanctum::actingAs(acApplicant());

    acMyApplicationCard()->assertOk()->assertJsonPath('data', null);
});

it('refuses an administrator who inherits the permission but is not an applicant', function () {
    // R09 inherits `application.view_status` (ADR-0008), but this is an R01 persona Card.
    actingAsRole(Role::SystemAdmin);

    acMyApplicationCard()->assertForbidden();
});

it('refuses a user who does not hold application.view_status', function () {
    // Every Card endpoint is independently authorized (ADR-0024), this one included: a Student holds
    // no admissions permission, so the server refuses the request whatever the client asked for.
    actingAsRole(Role::Student);

    acMyApplicationCard()->assertForbidden();
});

it('never exposes another applicant’s application, since there is no id to ask with', function () {
    $mine = acApplicant();
    $theirs = acApplicant();
    acApplication($theirs, ApplicationStatus::UnderSAReview, daysAgo: 3);

    Sanctum::actingAs($mine);

    acMyApplicationCard()->assertOk()->assertJsonPath('data', null);
});

it('refuses an unauthenticated request for the application card', function () {
    acMyApplicationCard()->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// Screening Queue — the count, the overdue count, and the oldest few (FR-ADM-005.5)
// ---------------------------------------------------------------------------

it('counts the applications pending screening and distinguishes the overdue ones', function () {
    acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 14); // 10 business days
    acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 14);
    acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 1);  // within the SLA
    // A different stage is a different queue.
    acApplication(acApplicant(), ApplicationStatus::UnderDeanReview, daysAgo: 30);

    actingAsRole(Role::StudentAffairs);

    acScreeningCard()->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta', [])
        ->assertJsonPath('data.pending_count', 3)
        ->assertJsonPath('data.overdue_count', 2)
        // The threshold travels with the counts so the SPA can say *why* without hard-coding it.
        ->assertJsonPath('data.sla_days', (int) config('admissions.sa_screening_sla_days'));
});

it('ages in business days, skipping the Egyptian weekend, exactly as the flagging command does', function () {
    acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 14);

    actingAsRole(Role::StudentAffairs);

    // 14 calendar days back is 10 business days: two whole weeks, two Fridays and two Saturdays
    // removed. A calendar-day count would have said 14.
    acScreeningCard()->assertOk()
        ->assertJsonPath('data.applications.0.age_days', 10)
        ->assertJsonPath('data.applications.0.is_overdue', true);
});

it('does not flag an application still inside the SLA', function () {
    acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 1);

    actingAsRole(Role::StudentAffairs);

    acScreeningCard()->assertOk()
        ->assertJsonPath('data.overdue_count', 0)
        ->assertJsonPath('data.applications.0.is_overdue', false);
});

it('lists the oldest few by name and age, and no more than three', function () {
    foreach (['A', 'B', 'C', 'D'] as $index => $name) {
        acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 20 - $index, nameEn: $name);
    }

    actingAsRole(Role::StudentAffairs);

    acScreeningCard()->assertOk()
        ->assertJsonPath('data.pending_count', 4)
        // A Card is a summary that links into the full queue, never the queue itself.
        ->assertJsonCount(3, 'data.applications')
        // Oldest first, and named in both languages so the SPA never receives a picked label.
        ->assertJsonPath('data.applications.0.name_en', 'A')
        ->assertJsonPath('data.applications.2.name_en', 'C')
        ->assertJsonPath('data.applications.0.name_ar', 'سارة المتقدمة');
});

it('orders the rows by the age it shows, not by when the application was finalized', function () {
    // A resubmitted application was finalized long ago but re-entered review just now, so ordering
    // by `finalized_at` would put the *youngest* wait at the top of the "oldest" list.
    $resubmitted = Application::factory()->underReview()->create([
        'user_id' => acApplicant()->id,
        'status' => ApplicationStatus::UnderSAReview,
        'name_en' => 'Resubmitted',
        'finalized_at' => now()->subDays(60),
    ]);
    ApplicationStatusTransition::factory()
        ->enteredAt(ApplicationStatus::UnderSAReview, now()->subDay())
        ->create(['application_id' => $resubmitted->id]);

    acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 14, nameEn: 'Waiting longest');

    actingAsRole(Role::StudentAffairs);

    acScreeningCard()->assertOk()
        ->assertJsonPath('data.applications.0.name_en', 'Waiting longest')
        ->assertJsonPath('data.applications.0.age_days', 10)
        ->assertJsonPath('data.applications.1.name_en', 'Resubmitted')
        ->assertJsonPath('data.applications.1.age_days', 1);
});

it('changes nothing about an overdue application — it is information only', function () {
    $overdue = acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 14);
    $transitionsBefore = $overdue->statusTransitions()->count();

    actingAsRole(Role::StudentAffairs);
    acScreeningCard()->assertOk()->assertJsonPath('data.overdue_count', 1);

    // No status moved, no timeline row was appended, nothing escalated.
    expect($overdue->refresh()->status)->toBe(ApplicationStatus::UnderSAReview)
        ->and($overdue->statusTransitions()->count())->toBe($transitionsBefore);
});

it('returns zeros and an empty list for an empty queue rather than erroring', function () {
    actingAsRole(Role::StudentAffairs);

    acScreeningCard()->assertOk()
        ->assertJsonPath('data.pending_count', 0)
        ->assertJsonPath('data.overdue_count', 0)
        ->assertJsonPath('data.applications', []);
});

it('refuses a user who does not hold application.screen', function () {
    acApplication(acApplicant(), ApplicationStatus::UnderSAReview, daysAgo: 14);
    Sanctum::actingAs(acApplicant());

    acScreeningCard()->assertForbidden();
});

it('refuses an unauthenticated request for the screening card', function () {
    acScreeningCard()->assertUnauthorized();
});
