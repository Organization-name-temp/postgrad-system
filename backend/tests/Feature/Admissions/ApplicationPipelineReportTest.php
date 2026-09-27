<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('serves the owner pipeline summary for a selected cycle and preserves zero stages', function () {
    $cycle = AdmissionCycle::factory()->create();
    $program = Program::factory()->create();
    Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id]);
    $advanced = Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id, 'status' => ApplicationStatus::UnderDeanReview]);
    ApplicationStatusTransition::factory()->create(['application_id' => $advanced->id, 'from_status' => ApplicationStatus::UnderSAReview, 'to_status' => ApplicationStatus::SAApproved]);
    ApplicationStatusTransition::factory()->create(['application_id' => $advanced->id, 'from_status' => ApplicationStatus::SAApproved, 'to_status' => ApplicationStatus::UnderDeanReview]);
    Application::factory()->underReview()->create(['primary_program_id' => $program->id]);
    actingAsRole(Role::StudentAffairs);
    $response = $this->getJson('/api/v1/admissions/reports/application-pipeline?admission_cycle_id='.$cycle->id);
    $response->assertOk()->assertJsonPath('data.total', 2)
        ->assertJsonPath('data.statuses.under_sa_review', 1)->assertJsonPath('data.statuses.under_dean_review', 1)
        ->assertJsonPath('data.statuses.accepted', 0);
    expect(array_keys($response->json('data.statuses')))->toBe(array_map(fn (ApplicationStatus $status) => $status->value, ApplicationStatus::cases()));
    $conversion = collect($response->json('data.conversions'))->first(fn ($row) => $row['from_status'] === 'under_sa_review' && $row['to_status'] === 'sa_approved');
    expect($conversion)->toBe(['from_status' => 'under_sa_review', 'to_status' => 'sa_approved', 'eligible' => 2, 'converted' => 1, 'rate' => 50]);
});

it('uses live Dean scope while Student Affairs filters only narrow the complete cohort', function () {
    $cycle = AdmissionCycle::factory()->create();
    $inside = Program::factory()->create();
    $outside = Program::factory()->create();
    foreach ([$inside, $outside] as $program) {
        Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id]);
    }
    $dean = actingAsRole(Role::Dean);
    $assignment = FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $inside->department->faculty_id]);
    $url = '/api/v1/admissions/reports/application-pipeline?admission_cycle_id='.$cycle->id;
    $this->getJson($url)->assertOk()->assertJsonPath('data.total', 1);
    $this->getJson($url.'&program_id='.$outside->id)->assertOk()->assertJsonPath('data.total', 0);
    $assignment->delete();
    $this->getJson($url)->assertOk()->assertJsonPath('data.total', 0);
    $dean->assignRole(Role::StudentAffairs);
    $this->getJson($url)->assertOk()->assertJsonPath('data.total', 2);
    $this->getJson($url.'&program_id='.$outside->id)->assertOk()->assertJsonPath('data.total', 1);
});

it('returns typed empty stages and null conversion rates without a cohort', function () {
    actingAsRole(Role::StudentAffairs);
    $response = $this->getJson('/api/v1/admissions/reports/application-pipeline?admission_cycle_id=999999&page=2&per_page=1');
    $response->assertOk()->assertJsonPath('data.total', 0)->assertJsonPath('meta.program_id', null)->assertJsonPath('meta.degree_level', null);
    expect(array_values($response->json('data.statuses')))->each->toBe(0);
    expect(array_column($response->json('data.conversions'), 'rate'))->each->toBeNull();
});

it('denies the pipeline report without its backend gate', function () {
    actingAsRole(Role::Applicant);
    $this->getJson('/api/v1/admissions/reports/application-pipeline?admission_cycle_id=1')->assertForbidden();
});

it('validates report criteria instead of ignoring unsupported filters', function () {
    actingAsRole(Role::StudentAffairs);
    foreach (['', '?admission_cycle_id=0', '?admission_cycle_id=1&program_id=-1', '?admission_cycle_id=1&degree_level=BSc', '?admission_cycle_id=1&status=accepted', '?admission_cycle_id=1&q=person'] as $query) {
        $this->getJson('/api/v1/admissions/reports/application-pipeline'.$query)->assertUnprocessable();
    }
});

it('drills a pipeline stage into exactly its ordinary application population', function () {
    $cycle = AdmissionCycle::factory()->create();
    $program = Program::factory()->create();
    $accepted = Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id, 'status' => ApplicationStatus::Accepted]);
    Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id]);
    Application::factory()->underReview()->create(['primary_program_id' => $program->id, 'status' => ApplicationStatus::Accepted]);
    actingAsRole(Role::StudentAffairs);
    $criteria = '?admission_cycle_id='.$cycle->id.'&program_id='.$program->id;
    $this->getJson('/api/v1/admissions/reports/application-pipeline'.$criteria)->assertOk()->assertJsonPath('data.statuses.accepted', 1);
    $this->getJson('/api/v1/applications'.$criteria.'&status=accepted')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $accepted->id);
    $export = $this->get('/api/v1/applications/export'.$criteria.'&status=accepted&per_page=1')->assertOk();
    $reader = new Reader;
    $reader->open($export->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(2)->and($rows[1][0])->toBe((string) $accepted->id)->and($rows[1])->toContain('Accepted');
    $this->getJson('/api/v1/applications'.$criteria)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.status', 'under_sa_review');
});

it('orders mixed pipeline stages by their domain values before derived pagination', function () {
    $cycle = AdmissionCycle::factory()->create();
    $program = Program::factory()->create();
    $ids = [];
    foreach ([ApplicationStatus::UnderSAReview, ApplicationStatus::Accepted, ApplicationStatus::Rejected] as $status) {
        $application = Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id, 'status' => $status]);
        ApplicationStatusTransition::factory()->enteredAt($status, now()->subDays(5))->create(['application_id' => $application->id]);
        $ids[$status->value] = $application->id;
    }
    actingAsRole(Role::StudentAffairs);
    $url = '/api/v1/applications?admission_cycle_id='.$cycle->id.'&status=under_sa_review,accepted,rejected&age_days_min=1&sort=status,age_days&per_page=1';
    $this->getJson($url)->assertOk()->assertJsonPath('meta.total', 3)->assertJsonPath('data.0.id', $ids['accepted']);
    $this->getJson($url.'&page=2')->assertOk()->assertJsonPath('data.0.id', $ids['rejected']);
    $this->getJson($url.'&page=3')->assertOk()->assertJsonPath('data.0.id', $ids['under_sa_review']);
});
