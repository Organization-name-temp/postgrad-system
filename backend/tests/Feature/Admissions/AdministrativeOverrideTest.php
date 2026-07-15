<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Administrative override (ticket 10, ADR-0015). A System/Super Administrator (R09/R10)
| oversees the whole pipeline through the *existing* reviewer / Dean / interview screens:
| they reach every Application across all Faculties (bypassing the Dean faculty-scope) and
| may record or override any interview evaluation (bypassing the assigned-interviewer
| row-level check), that write attributed to them in the Audit Log. Ordinary reviewers stay
| scoped. Everything is asserted as observable behaviour through /api/v1.
|
| Helpers are local to this file so it runs in isolation (the frontier runs single files).
*/

/** An application at the given status whose primary Program sits in the given Faculty. */
function applicationInFaculty(Faculty $faculty, ApplicationStatus $status): Application
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

/** A Dean (R07) assigned to the given Faculty. */
function deanOf(Faculty $faculty): User
{
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    return $dean;
}

/** A fresh faculty member (Instructor) — an interviewer holds no reviewer permission. */
function anInterviewer(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Instructor);

    return $user;
}

/** An interview whose slot is assigned to the given interviewer. */
function interviewAssignedTo(User $interviewer): Interview
{
    $slot = InterviewSlot::factory()->forInterviewer($interviewer)->create();

    return Interview::factory()->confirmed()->create(['interview_slot_id' => $slot->id]);
}

// --- Faculty-scope bypass: the Dean queues and per-application actions ---------------

it('shows an administrator every faculty’s Dean review queue, unscoped', function () {
    $facultyA = Faculty::factory()->create();
    $facultyB = Faculty::factory()->create();
    applicationInFaculty($facultyA, ApplicationStatus::UnderDeanReview);
    applicationInFaculty($facultyB, ApplicationStatus::UnderDeanReview);

    actingAsRole(Role::SystemAdmin);

    $this->getJson('/api/v1/dean/applications')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets an administrator act on an application in any faculty where a scoped Dean gets 403', function () {
    $mine = Faculty::factory()->create();
    $other = Faculty::factory()->create();
    $application = applicationInFaculty($other, ApplicationStatus::UnderDeanReview);

    // A Dean of a different Faculty cannot act on it.
    Sanctum::actingAs(deanOf($mine));
    $this->postJson("/api/v1/dean/applications/{$application->id}/review", ['action' => 'approve'])
        ->assertForbidden();
    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderDeanReview);

    // An administrator, holding no Faculty assignment at all, may.
    actingAsRole(Role::SystemAdmin);
    $this->postJson("/api/v1/dean/applications/{$application->id}/review", ['action' => 'approve'])
        ->assertOk()
        ->assertJsonPath('data.status', 'interview_scheduled');
});

it('shows an administrator every faculty’s Dean decisions queue, unscoped', function () {
    $facultyA = Faculty::factory()->create();
    $facultyB = Faculty::factory()->create();
    applicationInFaculty($facultyA, ApplicationStatus::InterviewCompleted);
    applicationInFaculty($facultyB, ApplicationStatus::InterviewCompleted);

    actingAsRole(Role::SuperAdmin);

    $this->getJson('/api/v1/dean/decisions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets an administrator record a decision on an out-of-faculty application where a scoped Dean gets 403', function () {
    $mine = Faculty::factory()->create();
    $other = Faculty::factory()->create();
    $application = applicationInFaculty($other, ApplicationStatus::InterviewCompleted);

    Sanctum::actingAs(deanOf($mine));
    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'waitlist',
        'waitlist_position' => 1,
    ])->assertForbidden();
    expect($application->fresh()->status)->toBe(ApplicationStatus::InterviewCompleted);

    actingAsRole(Role::SystemAdmin);
    $this->postJson("/api/v1/dean/applications/{$application->id}/decide", [
        'action' => 'waitlist',
        'waitlist_position' => 1,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'waitlisted');
});

// --- Row-level bypass: interview evaluation ------------------------------------------

it('lets an administrator record an evaluation for an interview not assigned to them, and audits it', function () {
    $interview = interviewAssignedTo(anInterviewer());

    $admin = actingAsRole(Role::SystemAdmin);

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", [
        'score' => 7,
        'notes' => 'Recorded on the interviewer’s behalf.',
    ])
        ->assertOk()
        ->assertJsonPath('data.evaluation_score', 7);

    expect($interview->fresh()->evaluation_score)->toBe(7);

    // The admin-authored score is attributed to the admin in the Audit Log (ADR-0015).
    $this->assertDatabaseHas('audit_log', [
        'action' => 'interview.evaluation_recorded_by_admin',
        'actor_user_id' => $admin->id,
        'subject_type' => 'interview',
        'subject_id' => (string) $interview->id,
    ]);
});

it('does not audit the assigned interviewer scoring their own interview', function () {
    $interviewer = anInterviewer();
    $interview = interviewAssignedTo($interviewer);
    Sanctum::actingAs($interviewer);

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", ['score' => 8])
        ->assertOk();

    // The interviewer attesting their own assignment writes nothing to the Audit Log.
    $this->assertDatabaseCount('audit_log', 0);
});

it('lists every interview to an administrator so they can reach the evaluation write-path', function () {
    interviewAssignedTo(anInterviewer());
    interviewAssignedTo(anInterviewer());

    actingAsRole(Role::SuperAdmin);

    $this->getJson('/api/v1/interviews/assigned')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

// --- Ordinary reviewers stay scoped (regression) -------------------------------------

it('keeps an ordinary interviewer scoped to their own assigned interviews', function () {
    $me = anInterviewer();
    interviewAssignedTo($me);
    interviewAssignedTo(anInterviewer()); // someone else's

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/interviews/assigned')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('keeps a privileged reviewer who is not the assigned interviewer out of the evaluation (403)', function () {
    $interview = interviewAssignedTo(anInterviewer());

    // Student Affairs holds interview.schedule but is not the interviewer, and is no admin.
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", ['score' => 5])
        ->assertForbidden();

    expect($interview->fresh()->evaluation_score)->toBeNull();
    $this->assertDatabaseCount('audit_log', 0);
});
