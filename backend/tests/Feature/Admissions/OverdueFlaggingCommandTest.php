<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

/*
| The `admissions:flag-overdue` console command (ticket 11) — the first console-command
| seam. It computes each in-review application's age in business days (Fri/Sat weekend
| excluded), compares it to the config SLA, and notifies the relevant reviewer for every
| application past it — Student Affairs for screening, the Faculty's Dean(s) for Dean
| review — while changing no application status. Driven at the highest point (command
| invocation → observable Notification rows via the notifications endpoint), never by
| reaching into command internals.
|
| Time is frozen so ages are deterministic. An entry placed 14 calendar days back is
| always 10 business days old (two whole weeks = two Fri + two Sat removed) — safely
| past the default 5-day SLA; an entry one day back is within it.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
});

/** A Student Affairs staffer (R05) — the screening-stage reviewer. */
function studentAffairsStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::StudentAffairs);

    return $user;
}

/**
 * An application resting in a review status, with a single timeline row recording that
 * it entered that status `$daysAgo` calendar days back (which drives the derived age).
 *
 * @param  array<string, mixed>  $overrides
 */
function agedReviewApplication(ApplicationStatus $status, int $daysAgo, array $overrides = []): Application
{
    $application = Application::factory()->underReview()->create(array_merge([
        'status' => $status,
    ], $overrides));

    ApplicationStatusTransition::factory()
        ->enteredAt($status, now()->subDays($daysAgo))
        ->create(['application_id' => $application->id]);

    return $application;
}

/** The notifications the given user can see, as the API returns them. */
function notificationsFor(User $user): TestResponse
{
    Sanctum::actingAs($user);

    return test()->getJson('/api/v1/notifications');
}

it('notifies Student Affairs when an application is overdue for screening', function () {
    $sa = studentAffairsStaff();
    $overdue = agedReviewApplication(ApplicationStatus::UnderSAReview, daysAgo: 14);

    $this->artisan('admissions:flag-overdue')->assertSuccessful();

    notificationsFor($sa)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'application.overdue')
        ->assertJsonPath('data.0.data.application_id', $overdue->id)
        ->assertJsonPath('data.0.data.stage', 'sa_screening');

    // Notify-only: the application has not moved.
    expect($overdue->fresh()->status)->toBe(ApplicationStatus::UnderSAReview);
});

it('does not flag an application still within the screening SLA', function () {
    $sa = studentAffairsStaff();
    agedReviewApplication(ApplicationStatus::UnderSAReview, daysAgo: 1);

    $this->artisan('admissions:flag-overdue')->assertSuccessful();

    notificationsFor($sa)->assertOk()->assertJsonCount(0, 'data');
});

it('notifies the faculty Dean when an application is overdue for Dean review', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::MSc,
    ]);

    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    // A Student Affairs staffer exists too — the Dean-stage overdue must reach the Dean,
    // not Student Affairs.
    $sa = studentAffairsStaff();

    $overdue = agedReviewApplication(ApplicationStatus::UnderDeanReview, daysAgo: 14, overrides: [
        'primary_program_id' => $program->id,
    ]);

    $this->artisan('admissions:flag-overdue')->assertSuccessful();

    notificationsFor($dean)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'application.overdue')
        ->assertJsonPath('data.0.data.application_id', $overdue->id)
        ->assertJsonPath('data.0.data.stage', 'dean_review');

    notificationsFor($sa)->assertOk()->assertJsonCount(0, 'data');

    expect($overdue->fresh()->status)->toBe(ApplicationStatus::UnderDeanReview);
});

it('does not notify a Dean about another faculty’s overdue application', function () {
    $mine = Faculty::factory()->create();
    $other = Faculty::factory()->create();
    $otherDepartment = Department::factory()->create(['faculty_id' => $other->id]);
    $otherProgram = Program::factory()->create([
        'department_id' => $otherDepartment->id,
        'degree_level' => DegreeLevel::MSc,
    ]);

    $deanOfMine = User::factory()->create();
    $deanOfMine->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $deanOfMine->id, 'faculty_id' => $mine->id]);

    agedReviewApplication(ApplicationStatus::UnderDeanReview, daysAgo: 14, overrides: [
        'primary_program_id' => $otherProgram->id,
    ]);

    $this->artisan('admissions:flag-overdue')->assertSuccessful();

    notificationsFor($deanOfMine)->assertOk()->assertJsonCount(0, 'data');
});

it('ignores applications that are not in a review stage', function () {
    $sa = studentAffairsStaff();
    // Long-idle applications in non-review states are not the command's concern.
    agedReviewApplication(ApplicationStatus::Draft, daysAgo: 30);
    agedReviewApplication(ApplicationStatus::Accepted, daysAgo: 30);

    $this->artisan('admissions:flag-overdue')->assertSuccessful();

    notificationsFor($sa)->assertOk()->assertJsonCount(0, 'data');
});

it('respects a configured screening SLA threshold', function () {
    config()->set('admissions.sa_screening_sla_days', 999);
    $sa = studentAffairsStaff();
    agedReviewApplication(ApplicationStatus::UnderSAReview, daysAgo: 14);

    $this->artisan('admissions:flag-overdue')->assertSuccessful();

    // 10 business days old, but the configured SLA is 999 — nothing is overdue.
    notificationsFor($sa)->assertOk()->assertJsonCount(0, 'data');
});

it('appends no status-transition rows while flagging', function () {
    studentAffairsStaff();
    $overdue = agedReviewApplication(ApplicationStatus::UnderSAReview, daysAgo: 14);

    $this->artisan('admissions:flag-overdue')->assertSuccessful();

    // The one entry-into-review row we seeded is still the only transition: the command
    // touched no workflow state.
    expect($overdue->statusTransitions()->count())->toBe(1);
});
