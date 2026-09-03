<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| The Admission Decisions Dashboard Card (phase 4.5, ticket 08) — how many Applications are waiting
| on the acting Dean's decision. Admissions decisions are the last step before an Applicant becomes
| a Student, and until now nothing told a Dean that applicants were waiting on them.
|
| Which applications those are, and the faculty scoping itself, are covered at their own seam by
| DeanDecisionQueueTest, so what is asserted here is that the Card *reads* that same derivation —
| one Dean's Card never counts another Faculty's Applications, and the Card's number is the queue's
| number — plus who is allowed to read it. Self-contained helpers (adecc* prefix) so a single-file
| run works.
*/

function adeccCard()
{
    return test()->getJson('/api/v1/admissions/cards/admission-decisions');
}

/** A Dean (R07 — holds `applicant.decide`) assigned to the given Faculty. */
function adeccDean(Faculty $faculty): User
{
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    return $dean;
}

/** An application in the given status whose primary Program sits in the given Faculty. */
function adeccApplication(Faculty $faculty, ApplicationStatus $status): Application
{
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::MSc,
    ]);

    return Application::factory()->interviewScheduled()->create([
        'status' => $status,
        'primary_program_id' => $program->id,
    ]);
}

// ---------------------------------------------------------------------------
// The count, and the queue it must agree with
// ---------------------------------------------------------------------------

it('counts the applications awaiting the Dean’s decision', function () {
    $faculty = Faculty::factory()->create();

    // A first decision is due on one, a resolution on the two held offers.
    adeccApplication($faculty, ApplicationStatus::InterviewCompleted);
    adeccApplication($faculty, ApplicationStatus::ConditionallyAccepted);
    adeccApplication($faculty, ApplicationStatus::Waitlisted);

    // Earlier stages and already-terminal outcomes are not awaiting a decision.
    adeccApplication($faculty, ApplicationStatus::UnderDeanReview);
    adeccApplication($faculty, ApplicationStatus::Accepted);

    Sanctum::actingAs(adeccDean($faculty));

    adeccCard()->assertOk()
        ->assertJsonStructure(['data', 'meta'])
        ->assertJsonPath('meta', [])
        ->assertJsonPath('data.pending_count', 3);
});

it('counts exactly what the decisions queue lists, never a second definition of “waiting”', function () {
    $faculty = Faculty::factory()->create();
    adeccApplication($faculty, ApplicationStatus::InterviewCompleted);
    adeccApplication($faculty, ApplicationStatus::Waitlisted);

    Sanctum::actingAs(adeccDean($faculty));

    // A Dean told "2 waiting" who opens the queue and finds 5 has been given worse than nothing,
    // so the two are asserted against each other rather than each against a literal.
    $queued = count(test()->getJson('/api/v1/dean/decisions')->assertOk()->json('data'));

    adeccCard()->assertOk()->assertJsonPath('data.pending_count', $queued);
});

it('excludes another Faculty’s applications from a Dean’s count', function () {
    $mine = Faculty::factory()->create();
    $other = Faculty::factory()->create();
    adeccApplication($mine, ApplicationStatus::InterviewCompleted);
    adeccApplication($other, ApplicationStatus::InterviewCompleted);
    adeccApplication($other, ApplicationStatus::Waitlisted);

    Sanctum::actingAs(adeccDean($mine));

    adeccCard()->assertOk()->assertJsonPath('data.pending_count', 1);
});

it('counts nothing for a Dean with no faculty assignment', function () {
    adeccApplication(Faculty::factory()->create(), ApplicationStatus::InterviewCompleted);

    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    Sanctum::actingAs($dean);

    adeccCard()->assertOk()->assertJsonPath('data.pending_count', 0);
});

it('returns zero rather than erroring when nothing is waiting', function () {
    Sanctum::actingAs(adeccDean(Faculty::factory()->create()));

    // The SPA renders the shared empty state from this, so an empty queue must be a 200 and a
    // zero — never an error and never an absent key.
    adeccCard()->assertOk()->assertJsonPath('data.pending_count', 0);
});

// ---------------------------------------------------------------------------
// Authorization — the server decides, not the client's choice of what to request
// ---------------------------------------------------------------------------

it('refuses a user without applicant.decide', function () {
    // Student Affairs holds application.screen + interview.schedule, never applicant.decide, so
    // they reach the screening Card and not this one.
    actingAsRole(Role::StudentAffairs);

    adeccCard()->assertForbidden();
});

it('refuses an unauthenticated request', function () {
    adeccCard()->assertUnauthorized();
});
