<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Interviewer evaluation (ticket 09) — row-level authorization. The evaluation surface
| is open to any authenticated user (an interviewer holds no reviewer permission), but
| each interview may be evaluated only by the interviewer its slot is assigned to;
| anyone else → 403, so evaluations stay trustworthy.
*/

/** An interview whose slot is assigned to the given interviewer. */
function interviewFor(User $interviewer): Interview
{
    $slot = InterviewSlot::factory()->forInterviewer($interviewer)->create();

    return Interview::factory()->confirmed()->create(['interview_slot_id' => $slot->id]);
}

it('lets the assigned interviewer record an evaluation', function () {
    $interviewer = facultyInterviewer();
    $interview = interviewFor($interviewer);
    Sanctum::actingAs($interviewer);

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", [
        'score' => 8,
        'notes' => 'Strong candidate; clear research direction.',
    ])
        ->assertOk()
        ->assertJsonPath('data.evaluation_score', 8)
        ->assertJsonPath('data.evaluation_notes', 'Strong candidate; clear research direction.');

    expect($interview->fresh()->evaluation_score)->toBe(8);
});

it('forbids anyone but the assigned interviewer from evaluating (403)', function () {
    $interview = interviewFor(facultyInterviewer());
    Sanctum::actingAs(facultyInterviewer()); // a different faculty member

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", ['score' => 5])
        ->assertForbidden();

    expect($interview->fresh()->evaluation_score)->toBeNull();
});

it('forbids a privileged reviewer who is not the assigned interviewer (403)', function () {
    $interview = interviewFor(facultyInterviewer());
    actingAsRole(Role::StudentAffairs); // holds interview.schedule, but not the interviewer

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", ['score' => 5])
        ->assertForbidden();
});

it('validates the score is within range', function () {
    $interviewer = facultyInterviewer();
    $interview = interviewFor($interviewer);
    Sanctum::actingAs($interviewer);

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", ['score' => 11])
        ->assertStatus(422)
        ->assertJsonValidationErrors('score');

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('score');
});

it('refuses to evaluate a completed interview (409)', function () {
    $interviewer = facultyInterviewer();
    $interview = interviewFor($interviewer);
    $interview->markCompleted();
    Sanctum::actingAs($interviewer);

    $this->postJson("/api/v1/interviews/{$interview->id}/evaluation", ['score' => 8])
        ->assertStatus(409);
});

it('lists only the interviews assigned to the signed-in interviewer', function () {
    $me = facultyInterviewer();
    $mine = interviewFor($me);
    interviewFor(facultyInterviewer()); // someone else's interview

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/interviews/assigned')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.applicant.national_id', fn ($value) => $value !== null);
});
