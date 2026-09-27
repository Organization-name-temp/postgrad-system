<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

/*
| Interview scheduling + completion (ticket 09). Student Affairs books an approved
| applicant (interview_scheduled) into an open slot — which creates the applicant's one
| Interview and notifies them — then, after the interview, marks it complete, advancing
| the application to interview_completed. The state guards (right status, not already
| booked, slot still open, single interview per application) surface as 409s.
*/

it('lists Dean-approved applicants awaiting scheduling, excluding already-booked ones', function () {
    $awaiting = Application::factory()->interviewScheduled()->create();
    $booked = Interview::factory()->create(); // its application is interview_scheduled but booked
    Application::factory()->underReview()->create(); // wrong status — excluded

    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/applications/awaiting-interview')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $awaiting->id);

    expect($booked->application_id)->not->toBe($awaiting->id);
});

it('refuses the scheduling worklist to a user without interview.schedule', function () {
    actingAsRole(Role::Applicant);

    $this->getJson('/api/v1/applications/awaiting-interview')->assertForbidden();
});

it('filters and pages the university scheduling worklist for a Dean without faculty assignments', function () {
    $program = Program::factory()->create();
    $first = Application::factory()->interviewScheduled()->create(['primary_program_id' => $program->id, 'name_en' => 'Scheduling A']);
    $second = Application::factory()->interviewScheduled()->create(['primary_program_id' => $program->id, 'name_en' => 'Scheduling B']);
    $booked = Application::factory()->interviewScheduled()->create(['primary_program_id' => $program->id, 'name_en' => 'Scheduling C']);
    Interview::factory()->create(['application_id' => $booked->id]);
    Application::factory()->interviewScheduled()->create(['name_en' => 'Other applicant']);
    actingAsRole(Role::Dean);
    $criteria = '?q=Scheduling&program_id='.$program->id.'&sort=name_en&per_page=1';
    $this->getJson('/api/v1/applications/awaiting-interview'.$criteria)->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id)->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/applications/awaiting-interview'.$criteria.'&page=2')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id);
});

it('exports all unbooked scheduling matches for a Dean and audits the worklist resource', function () {
    $first = Application::factory()->interviewScheduled()->create(['name_en' => 'Export scheduling A']);
    $second = Application::factory()->interviewScheduled()->create(['name_en' => 'Export scheduling B']);
    $booked = Application::factory()->interviewScheduled()->create(['name_en' => 'Export scheduling C']);
    Interview::factory()->create(['application_id' => $booked->id]);
    $actor = actingAsRole(Role::Dean);
    $response = $this->get('/api/v1/applications/awaiting-interview/export?q=Export&sort=name_en&per_page=1')->assertOk();
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
    expect($audit->actor_user_id)->toBe($actor->id)->and($audit->metadata['resource'])->toBe('applications/awaiting-interview')
        ->and($audit->metadata['row_count'])->toBe(2);
    actingAsRole(Role::Applicant);
    $this->get('/api/v1/applications/awaiting-interview/export')->assertForbidden();
});

it('lists booked interviews with applicant and slot for Student Affairs', function () {
    $interview = Interview::factory()->confirmed()->create();
    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/interviews')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $interview->id)
        ->assertJsonPath('data.0.applicant.national_id', fn ($value) => $value !== null)
        ->assertJsonPath('data.0.slot.id', $interview->interview_slot_id);
});

it('schedules an approved applicant into an open slot and notifies them', function () {
    $application = Application::factory()->interviewScheduled()->create();
    $applicant = User::query()->findOrFail($application->user_id);
    $slot = InterviewSlot::factory()->create();

    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/interview", ['interview_slot_id' => $slot->id])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending_confirmation')
        ->assertJsonPath('data.application_id', $application->id)
        ->assertJsonPath('data.slot.id', $slot->id);

    expect(Interview::query()->where('application_id', $application->id)->exists())->toBeTrue();
    // The application stays interview_scheduled — booking is not a status transition.
    expect($application->fresh()->status)->toBe(ApplicationStatus::InterviewScheduled);

    Sanctum::actingAs($applicant);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'interview.scheduled');
});

it('refuses to schedule an application that is not awaiting interview scheduling (409)', function () {
    $application = Application::factory()->underReview()->create();
    $slot = InterviewSlot::factory()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/interview", ['interview_slot_id' => $slot->id])
        ->assertStatus(409);

    expect(Interview::query()->count())->toBe(0);
});

it('refuses to schedule an application that already has an interview (409)', function () {
    $interview = Interview::factory()->create();
    $slot = InterviewSlot::factory()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$interview->application_id}/interview", ['interview_slot_id' => $slot->id])
        ->assertStatus(409);
});

it('refuses to schedule into a slot that is already taken (409)', function () {
    $taken = InterviewSlot::factory()->create();
    Interview::factory()->create(['interview_slot_id' => $taken->id]);
    $application = Application::factory()->interviewScheduled()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/interview", ['interview_slot_id' => $taken->id])
        ->assertStatus(409);
});

it('validates the slot exists', function () {
    $application = Application::factory()->interviewScheduled()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/interview", ['interview_slot_id' => 99999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('interview_slot_id');
});

it('marks an interview complete, advancing the application to interview_completed', function () {
    $interview = Interview::factory()->confirmed()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/interviews/{$interview->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.completed_at', fn ($value) => $value !== null);

    expect($interview->fresh()->completed_at)->not->toBeNull();
    expect(Application::query()->findOrFail($interview->application_id)->status)
        ->toBe(ApplicationStatus::InterviewCompleted);
});

it('refuses to complete an interview twice (409)', function () {
    $interview = Interview::factory()->confirmed()->create();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/interviews/{$interview->id}/complete")->assertOk();
    $this->postJson("/api/v1/interviews/{$interview->id}/complete")->assertStatus(409);
});

it('refuses the scheduling surface to a user without interview.schedule', function () {
    $application = Application::factory()->interviewScheduled()->create();
    $slot = InterviewSlot::factory()->create();
    actingAsRole(Role::Applicant);

    $this->postJson("/api/v1/applications/{$application->id}/interview", ['interview_slot_id' => $slot->id])
        ->assertForbidden();
});
