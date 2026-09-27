<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

/*
| Dean decisions queue (ticket 12) — the post-interview worklist behind the Dean's
| final-decision screen. It lists the applications awaiting a decision: those resting
| at `interview_completed` (a first decision is due) and those held at
| `conditionally_accepted` / `waitlisted` (a resolution is due), scoped to the Dean's
| assigned Faculties and ordered oldest-first — the same faculty-scoping and shape as
| the sibling Dean review queue (ticket 08). Gated on `applicant.decide` (Dean only).
| Asserted as observable HTTP behaviour, never controller internals.
*/

/** A Dean (R07 — holds `applicant.decide`) assigned to the given Faculty. */
function decisionQueueDean(Faculty $faculty): User
{
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $faculty->id]);

    return $dean;
}

/** An application in the given status whose primary Program sits in the given Faculty. */
function decisionQueueApplication(Faculty $faculty, ApplicationStatus $status): Application
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

it('lists the applications awaiting the Dean’s decision for their faculty', function () {
    $faculty = Faculty::factory()->create();
    $completed = decisionQueueApplication($faculty, ApplicationStatus::InterviewCompleted);
    $conditional = decisionQueueApplication($faculty, ApplicationStatus::ConditionallyAccepted);
    $waitlisted = decisionQueueApplication($faculty, ApplicationStatus::Waitlisted);

    // Earlier stages and already-terminal outcomes are not awaiting a decision.
    decisionQueueApplication($faculty, ApplicationStatus::UnderDeanReview);
    decisionQueueApplication($faculty, ApplicationStatus::Accepted);

    Sanctum::actingAs(decisionQueueDean($faculty));

    $ids = $this->getJson('/api/v1/dean/decisions')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->json('data.*.id');

    expect($ids)->toEqualCanonicalizing([$completed->id, $conditional->id, $waitlisted->id]);
});

it('excludes applications outside the Dean’s faculties', function () {
    $mine = Faculty::factory()->create();
    $other = Faculty::factory()->create();
    $ours = decisionQueueApplication($mine, ApplicationStatus::InterviewCompleted);
    decisionQueueApplication($other, ApplicationStatus::InterviewCompleted);

    Sanctum::actingAs(decisionQueueDean($mine));

    $this->getJson('/api/v1/dean/decisions')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $ours->id);
});

it('filters decision state before stable pages without widening a multirole Dean scope', function () {
    $faculty = Faculty::factory()->create();
    $first = decisionQueueApplication($faculty, ApplicationStatus::Waitlisted);
    $second = decisionQueueApplication($faculty, ApplicationStatus::Waitlisted);
    decisionQueueApplication($faculty, ApplicationStatus::InterviewCompleted);
    $outside = decisionQueueApplication(Faculty::factory()->create(), ApplicationStatus::Waitlisted);
    $dean = decisionQueueDean($faculty);
    $dean->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($dean);

    $this->getJson('/api/v1/dean/decisions?status=waitlisted&sort=id&per_page=1&page=2')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/dean/decisions?status=waitlisted&sort=id&per_page=1')
        ->assertOk()->assertJsonPath('data.0.id', $first->id);
    $this->getJson('/api/v1/dean/decisions?program_id='.$outside->primary_program_id)
        ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
});

it('is empty for a Dean with no faculty assignment', function () {
    $faculty = Faculty::factory()->create();
    decisionQueueApplication($faculty, ApplicationStatus::InterviewCompleted);

    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    Sanctum::actingAs($dean);

    $this->getJson('/api/v1/dean/decisions')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('exports the filtered decision state for every in-scope row and audits the decision resource', function () {
    $faculty = Faculty::factory()->create();
    $first = decisionQueueApplication($faculty, ApplicationStatus::Waitlisted);
    $second = decisionQueueApplication($faculty, ApplicationStatus::Waitlisted);
    decisionQueueApplication($faculty, ApplicationStatus::InterviewCompleted);
    decisionQueueApplication(Faculty::factory()->create(), ApplicationStatus::Waitlisted);
    $dean = decisionQueueDean($faculty);
    $dean->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($dean);
    $response = $this->get('/api/v1/dean/decisions/export?status=waitlisted&sort=id&per_page=1')->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(3)->and($rows[1][0])->toBe((string) $first->id)->and($rows[2][0])->toBe((string) $second->id);
    $audit = AuditLog::query()->where('action', 'admissions.applications_exported')->sole();
    expect($audit->metadata['resource'])->toBe('dean/decisions')->and($audit->metadata['row_count'])->toBe(2);
});

it('refuses the decisions queue to a user without applicant.decide (403)', function () {
    $faculty = Faculty::factory()->create();
    decisionQueueApplication($faculty, ApplicationStatus::InterviewCompleted);

    // Student Affairs holds application.screen + interview.schedule, never applicant.decide.
    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/dean/decisions')->assertForbidden();
});
