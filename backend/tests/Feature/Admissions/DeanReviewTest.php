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
| Dean review + Dean↔Faculty scoping (ticket 08). Driven end-to-end through /api/v1:
| a Dean sees only their Faculty's SA-approved applications, approves them for
| interview or rejects them (both notify the applicant), and is refused (403) on any
| application outside their Faculty. The Faculty is resolved application → program →
| faculty through the Catalog facade (ADR-0012), the Dean's Faculties through the
| Identity assignment facade.
*/

/**
 * A Dean (R07) assigned to the given opaque Faculty id.
 */
function deanAssignedTo(int $facultyId): User
{
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $facultyId]);

    return $dean;
}

/**
 * An `under_dean_review` application whose primary Program sits in the given Faculty.
 * Returns [Application, applicant User].
 *
 * @param  array<string, mixed>  $overrides
 * @return array{0: Application, 1: User}
 */
function underDeanReviewApplication(int $facultyId, array $overrides = []): array
{
    $department = Department::factory()->create(['faculty_id' => $facultyId]);
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'degree_level' => DegreeLevel::MSc,
    ]);
    $application = Application::factory()->underReview()->create(array_merge([
        'status' => ApplicationStatus::UnderDeanReview,
        'primary_program_id' => $program->id,
    ], $overrides));

    return [$application, User::query()->findOrFail($application->user_id)];
}

it('lists only the Dean’s own faculty applications', function () {
    $faculty = Faculty::factory()->create();
    $otherFaculty = Faculty::factory()->create();
    [$mine] = underDeanReviewApplication($faculty->id);
    underDeanReviewApplication($otherFaculty->id); // another faculty — must not appear

    Sanctum::actingAs(deanAssignedTo($faculty->id));

    $this->getJson('/api/v1/dean/applications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.status', 'under_dean_review');
});

it('filters and pages inside the Dean faculty scope even when the Dean also holds Student Affairs', function () {
    $faculty = Faculty::factory()->create();
    $otherFaculty = Faculty::factory()->create();
    [$first] = underDeanReviewApplication($faculty->id, ['name_en' => 'Queue Candidate A']);
    [$second] = underDeanReviewApplication($faculty->id, ['name_en' => 'Queue Candidate B']);
    [$outside] = underDeanReviewApplication($otherFaculty->id, ['name_en' => 'Queue Candidate C']);
    $dean = deanAssignedTo($faculty->id);
    $dean->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($dean);

    $this->getJson('/api/v1/dean/applications?q=Candidate&degree_level=MSc&sort=name_en&per_page=1&page=2')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id)
        ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.current_page', 2);
    $this->getJson('/api/v1/dean/applications?program_id='.$outside->primary_program_id)
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/dean/applications?q=Candidate&sort=name_en&per_page=1')
        ->assertOk()->assertJsonPath('data.0.id', $first->id);
});

it('shows an empty queue to a Dean with no assigned faculty', function () {
    $faculty = Faculty::factory()->create();
    underDeanReviewApplication($faculty->id);

    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean); // no faculty assignment
    Sanctum::actingAs($dean);

    $this->getJson('/api/v1/dean/applications')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('narrows the existing Dean scope by Faculty IDs without disclosing outside rows', function () {
    $faculty = Faculty::factory()->create();
    $secondFaculty = Faculty::factory()->create();
    $outsideFaculty = Faculty::factory()->create();
    [$mine] = underDeanReviewApplication($faculty->id);
    underDeanReviewApplication($secondFaculty->id);
    underDeanReviewApplication($outsideFaculty->id);
    $dean = deanAssignedTo($faculty->id);
    $dean->assignRole(Role::StudentAffairs);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $secondFaculty->id]);
    Sanctum::actingAs($dean);

    $this->getJson('/api/v1/dean/applications?faculty_id='.$faculty->id)
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $mine->id);
    $this->getJson('/api/v1/dean/applications?faculty_id='.$outsideFaculty->id)
        ->assertOk()->assertJsonPath('meta.total', 0)->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/dean/applications?faculty_id='.$faculty->id.','.$secondFaculty->id.','.$outsideFaculty->id)
        ->assertOk()->assertJsonPath('meta.total', 2);
    $response = $this->get('/api/v1/dean/applications/export?faculty_id='.$faculty->id.'&per_page=1')->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(2)->and($rows[1][0])->toBe((string) $mine->id);
    $audit = AuditLog::query()->where('action', 'admissions.applications_exported')->sole();
    expect($audit->metadata['criteria']['faculty_id'])->toBe([(string) $faculty->id])
        ->and($audit->metadata['row_count'])->toBe(1);
});

it('exports every matching Dean review row without disclosing another faculty to a multirole Dean', function () {
    $faculty = Faculty::factory()->create();
    [$first] = underDeanReviewApplication($faculty->id, ['name_en' => 'Export A']);
    [$second] = underDeanReviewApplication($faculty->id, ['name_en' => 'Export B']);
    underDeanReviewApplication(Faculty::factory()->create()->id, ['name_en' => 'Export C']);
    $dean = deanAssignedTo($faculty->id);
    $dean->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($dean);
    $response = $this->get('/api/v1/dean/applications/export?q=Export&sort=name_en&per_page=1')->assertOk();
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
    expect($audit->metadata['resource'])->toBe('dean/applications')->and($audit->metadata['row_count'])->toBe(2);
});

it('approves an application for interview and notifies the applicant', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = underDeanReviewApplication($faculty->id);
    Sanctum::actingAs(deanAssignedTo($faculty->id));

    $this->postJson("/api/v1/dean/applications/{$application->id}/review", ['action' => 'approve'])
        ->assertOk()
        ->assertJsonPath('data.status', 'interview_scheduled');

    expect($application->fresh()->status)->toBe(ApplicationStatus::InterviewScheduled);

    Sanctum::actingAs($applicant);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'application.approved_for_interview');
});

it('rejects an application with a reason and notifies the applicant', function () {
    $faculty = Faculty::factory()->create();
    [$application, $applicant] = underDeanReviewApplication($faculty->id);
    Sanctum::actingAs(deanAssignedTo($faculty->id));

    $this->postJson("/api/v1/dean/applications/{$application->id}/review", [
        'action' => 'reject',
        'note' => 'The panel did not recommend admission.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    // The reason is on the append-only timeline.
    $this->getJson("/api/v1/applications/{$application->id}/timeline");
    Sanctum::actingAs($applicant);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'decision.recorded');
});

it('refuses a Dean acting on an application outside their faculty (403)', function () {
    $mine = Faculty::factory()->create();
    $other = Faculty::factory()->create();
    [$application] = underDeanReviewApplication($other->id);

    Sanctum::actingAs(deanAssignedTo($mine->id));

    $this->postJson("/api/v1/dean/applications/{$application->id}/review", ['action' => 'approve'])
        ->assertForbidden();

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderDeanReview);
});

it('requires a reason to reject', function () {
    $faculty = Faculty::factory()->create();
    [$application] = underDeanReviewApplication($faculty->id);
    Sanctum::actingAs(deanAssignedTo($faculty->id));

    $this->postJson("/api/v1/dean/applications/{$application->id}/review", ['action' => 'reject'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');
});

it('refuses to act on an in-faculty application that is not under Dean review (409)', function () {
    $faculty = Faculty::factory()->create();
    // In the Dean's faculty, but still with Student Affairs — the Dean cannot act yet.
    [$application] = underDeanReviewApplication($faculty->id, ['status' => ApplicationStatus::UnderSAReview]);
    Sanctum::actingAs(deanAssignedTo($faculty->id));

    $this->postJson("/api/v1/dean/applications/{$application->id}/review", ['action' => 'approve'])
        ->assertStatus(409);

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderSAReview);
});

it('refuses the Dean queue to a user without application.screen', function () {
    actingAsRole(Role::Applicant);

    $this->getJson('/api/v1/dean/applications')->assertForbidden();
});
