<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\InterviewStatus;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/*
| The applicant's side of their interview (ticket 09). Once booked, the applicant sees
| the details and either confirms it or reschedules it exactly once into another open
| slot; a second reschedule is refused (409, BR-11). All acts on the signed-in
| applicant's own interview — no id in the path.
*/

/** Sign in as the applicant who owns the given interview. */
function actingAsApplicantOf(Interview $interview): User
{
    $applicant = User::query()->findOrFail(
        $interview->application()->firstOrFail()->user_id,
    );
    Sanctum::actingAs($applicant);

    return $applicant;
}

it('shows the applicant their booked interview', function () {
    $interview = Interview::factory()->create();
    actingAsApplicantOf($interview);

    $this->getJson('/api/v1/application/interview')
        ->assertOk()
        ->assertJsonPath('data.id', $interview->id)
        ->assertJsonPath('data.status', 'pending_confirmation')
        ->assertJsonPath('data.can_reschedule', true)
        ->assertJsonPath('data.slot.id', $interview->interview_slot_id);
});

it('404s when the applicant has no interview', function () {
    [$user] = draftApplicant();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/application/interview')->assertNotFound();
});

it('confirms a pending interview', function () {
    $interview = Interview::factory()->create();
    actingAsApplicantOf($interview);

    $this->postJson('/api/v1/application/interview/confirm')
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    expect($interview->fresh()->status)->toBe(InterviewStatus::Confirmed);
});

it('refuses to confirm an already-confirmed interview (409)', function () {
    $interview = Interview::factory()->confirmed()->create();
    actingAsApplicantOf($interview);

    $this->postJson('/api/v1/application/interview/confirm')->assertStatus(409);
});

it('reschedules once into another open slot', function () {
    $interview = Interview::factory()->confirmed()->create();
    $target = InterviewSlot::factory()->create();
    actingAsApplicantOf($interview);

    $this->postJson('/api/v1/application/interview/reschedule', ['interview_slot_id' => $target->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'pending_confirmation')
        ->assertJsonPath('data.reschedule_count', 1)
        ->assertJsonPath('data.can_reschedule', false)
        ->assertJsonPath('data.slot.id', $target->id);

    expect($interview->fresh()->interview_slot_id)->toBe($target->id);
});

it('refuses a second reschedule (409)', function () {
    $interview = Interview::factory()->rescheduledOnce()->create();
    $target = InterviewSlot::factory()->create();
    actingAsApplicantOf($interview);

    $this->postJson('/api/v1/application/interview/reschedule', ['interview_slot_id' => $target->id])
        ->assertStatus(409);
});

it('refuses to reschedule into a taken slot', function () {
    $interview = Interview::factory()->create();
    $taken = InterviewSlot::factory()->create();
    Interview::factory()->create(['interview_slot_id' => $taken->id]);
    actingAsApplicantOf($interview);

    $this->postJson('/api/v1/application/interview/reschedule', ['interview_slot_id' => $taken->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('interview_slot_id');
});

it('lists the open slots the applicant could reschedule into', function () {
    $interview = Interview::factory()->create(); // its own slot is taken
    InterviewSlot::factory()->create();          // one open slot
    actingAsApplicantOf($interview);

    $this->getJson('/api/v1/application/interview/slots')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_open', true);
});
