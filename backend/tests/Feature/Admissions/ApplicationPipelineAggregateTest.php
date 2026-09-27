<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;
use Modules\Admissions\Public\ApplicationPipelines;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Public\Enums\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

it('aggregates the scoped cycle statuses and measured transition conversion without inventing history', function () {
    $cycle = AdmissionCycle::factory()->create();
    $program = Program::factory()->create();
    Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id]);
    $advanced = Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id, 'status' => ApplicationStatus::UnderDeanReview]);
    ApplicationStatusTransition::factory()->create(['application_id' => $advanced->id, 'from_status' => ApplicationStatus::UnderSAReview, 'to_status' => ApplicationStatus::SAApproved]);
    ApplicationStatusTransition::factory()->create(['application_id' => $advanced->id, 'from_status' => ApplicationStatus::SAApproved, 'to_status' => ApplicationStatus::UnderDeanReview]);
    // Another Cycle must not enter this selection.
    Application::factory()->underReview()->create(['primary_program_id' => $program->id]);
    $actor = actingAsRole(Role::StudentAffairs);
    $report = app(ApplicationPipelines::class)->summarize($actor->id, $cycle->id);
    expect($report->total)->toBe(2)->and($report->statuses['under_sa_review'])->toBe(1)
        ->and($report->statuses['under_dean_review'])->toBe(1)->and($report->statuses['accepted'])->toBe(0);
    $conversion = collect($report->conversions)->first(fn ($row) => $row['from_status'] === 'under_sa_review' && $row['to_status'] === 'sa_approved');
    expect($conversion)->toBe(['from_status' => 'under_sa_review', 'to_status' => 'sa_approved', 'eligible' => 2, 'converted' => 1, 'rate' => 50.0]);
    $empty = app(ApplicationPipelines::class)->summarize($actor->id, 999999);
    expect($empty->total)->toBe(0)->and(array_sum($empty->statuses))->toBe(0)
        ->and(array_column($empty->conversions, 'rate'))->each->toBeNull();
});

it('narrows live Dean scope and combines Student Affairs oversight in pipeline aggregates', function () {
    $cycle = AdmissionCycle::factory()->create();
    $inside = Program::factory()->create();
    $outside = Program::factory()->create();
    foreach ([$inside, $outside] as $program) {
        Application::factory()->underReview()->create(['admission_cycle_id' => $cycle->id, 'primary_program_id' => $program->id]);
    }
    $dean = actingAsRole(Role::Dean);
    $assignment = FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $inside->department->faculty_id]);
    $pipelines = app(ApplicationPipelines::class);
    expect($pipelines->summarize($dean->id, $cycle->id)->total)->toBe(1)
        ->and($pipelines->summarize($dean->id, $cycle->id, $outside->id)->total)->toBe(0);
    $assignment->delete();
    expect($pipelines->summarize($dean->id, $cycle->id)->total)->toBe(0);
    $dean->assignRole(Role::StudentAffairs);
    expect($pipelines->summarize($dean->id, $cycle->id)->total)->toBe(2);
});

it('refuses the pipeline aggregate to a user without the screening gate', function () {
    $actor = actingAsRole(Role::Applicant);
    try {
        app(ApplicationPipelines::class)->summarize($actor->id, 1);
        test()->fail('Applicant must not read a pipeline population.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(403);
    }
});
