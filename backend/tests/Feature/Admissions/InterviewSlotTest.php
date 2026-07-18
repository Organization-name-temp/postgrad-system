<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Interview-slot pool management (ticket 09). Driven through /api/v1: Student Affairs
| (holding interview.schedule) lists assignable faculty interviewers, creates slots
| assigned to them, and edits/deletes only open ones. A non-faculty interviewer is
| refused (422); a booked slot cannot be changed (409); a non-privileged user cannot
| reach the surface at all (403).
*/

/** A valid slot-create payload assigned to the given (or a fresh) interviewer. */
function slotPayload(?User $interviewer = null, array $overrides = []): array
{
    $interviewer ??= facultyInterviewer();

    return array_merge([
        'scheduled_at' => now()->addDays(5)->setTime(10, 0)->toIso8601String(),
        'duration_minutes' => 30,
        'interviewer_id' => $interviewer->id,
        'mode' => 'in_person',
        'location' => 'Building C, Room 204',
    ], $overrides);
}

it('lists the assignable faculty interviewers', function () {
    $one = facultyInterviewer();
    $two = facultyInterviewer();
    User::factory()->create()->assignRole(Role::Applicant); // not faculty — excluded

    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/interview-slots/interviewers')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $one->id])
        ->assertJsonFragment(['id' => $two->id]);
});

it('creates an interview slot assigned to a faculty interviewer', function () {
    $interviewer = facultyInterviewer();
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/interview-slots', slotPayload($interviewer))
        ->assertCreated()
        ->assertJsonPath('data.mode', 'in_person')
        ->assertJsonPath('data.interviewer_id', $interviewer->id)
        ->assertJsonPath('data.interviewer.id', $interviewer->id)
        ->assertJsonPath('data.is_open', true);

    expect(InterviewSlot::query()->count())->toBe(1);
});

it('creates a virtual slot with a meeting link', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/interview-slots', slotPayload(overrides: [
        'mode' => 'virtual',
        'location' => null,
        'meeting_link' => 'https://meet.ejust.edu.eg/abc',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.mode', 'virtual')
        ->assertJsonPath('data.meeting_link', 'https://meet.ejust.edu.eg/abc');
});

it('rejects a slot whose interviewer is not a faculty member', function () {
    $notFaculty = User::factory()->create();
    $notFaculty->assignRole(Role::Applicant);
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/interview-slots', slotPayload(overrides: ['interviewer_id' => $notFaculty->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('interviewer_id');
});

it('requires a location for an in-person slot and a link for a virtual one', function () {
    actingAsRole(Role::StudentAffairs);

    $this->postJson('/api/v1/interview-slots', slotPayload(overrides: ['mode' => 'in_person', 'location' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('location');

    $this->postJson('/api/v1/interview-slots', slotPayload(overrides: ['mode' => 'virtual', 'location' => null]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('meeting_link');
});

it('lists the whole slot pool with open state', function () {
    $slot = InterviewSlot::factory()->create();
    Interview::factory()->create(['interview_slot_id' => $slot->id]); // this slot is now taken
    InterviewSlot::factory()->create(); // an open one

    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/interview-slots')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('updates an open slot', function () {
    $slot = InterviewSlot::factory()->create();
    actingAsRole(Role::StudentAffairs);

    $this->patchJson("/api/v1/interview-slots/{$slot->id}", slotPayload(overrides: ['duration_minutes' => 45]))
        ->assertOk()
        ->assertJsonPath('data.duration_minutes', 45);
});

it('refuses to update a slot that has an interview booked (409)', function () {
    $slot = InterviewSlot::factory()->create();
    Interview::factory()->create(['interview_slot_id' => $slot->id]);
    actingAsRole(Role::StudentAffairs);

    $this->patchJson("/api/v1/interview-slots/{$slot->id}", slotPayload(overrides: ['duration_minutes' => 45]))
        ->assertStatus(409);
});

it('deletes an open slot', function () {
    $slot = InterviewSlot::factory()->create();
    actingAsRole(Role::StudentAffairs);

    $this->deleteJson("/api/v1/interview-slots/{$slot->id}")->assertNoContent();

    expect(InterviewSlot::query()->count())->toBe(0);
});

it('refuses to delete a slot that has an interview booked (409)', function () {
    $slot = InterviewSlot::factory()->create();
    Interview::factory()->create(['interview_slot_id' => $slot->id]);
    actingAsRole(Role::StudentAffairs);

    $this->deleteJson("/api/v1/interview-slots/{$slot->id}")->assertStatus(409);

    expect(InterviewSlot::query()->count())->toBe(1);
});

it('refuses the slot surface to a user without interview.schedule', function () {
    actingAsRole(Role::Applicant);

    $this->getJson('/api/v1/interview-slots')->assertForbidden();
    $this->postJson('/api/v1/interview-slots', slotPayload())->assertForbidden();
});
