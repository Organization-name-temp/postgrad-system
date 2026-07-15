<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\InterviewStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Internal\Models\User;

/*
| The `admissions:send-interview-reminders` console command (ticket 11). It nudges both
| the applicant and the assigned interviewer about every upcoming interview whose start
| falls inside the reminder window (default 48h), and changes no status. Driven at the
| highest point — command invocation → observable Notification rows via the notifications
| endpoint. Time is frozen so "upcoming" is deterministic.
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
});

/**
 * An interview booked into a slot at `$scheduledAt` for a fresh applicant and (unless
 * given) a fresh interviewer. Returns [Interview, applicant User, interviewer User, slot].
 *
 * @return array{0: Interview, 1: User, 2: User, 3: InterviewSlot}
 */
function bookedInterviewAt(Carbon $scheduledAt, ?User $interviewer = null): array
{
    $interviewer ??= facultyInterviewer();
    $slot = InterviewSlot::factory()->forInterviewer($interviewer)->create([
        'scheduled_at' => $scheduledAt,
    ]);
    $application = Application::factory()->interviewScheduled()->create();
    $interview = Interview::factory()->create([
        'application_id' => $application->id,
        'interview_slot_id' => $slot->id,
    ]);

    return [$interview, userOf($application), $interviewer, $slot];
}

/** The notifications the given user can see, as the API returns them. */
function reminderNotificationsFor(User $user): TestResponse
{
    Sanctum::actingAs($user);

    return test()->getJson('/api/v1/notifications');
}

it('reminds both the applicant and the interviewer of an upcoming interview', function () {
    [$interview, $applicant, $interviewer, $slot] = bookedInterviewAt(now()->addHours(24));

    $this->artisan('admissions:send-interview-reminders')->assertSuccessful();

    // The exact row each recipient receives — type and full payload, not just a count.
    $expectedScheduledAt = $slot->scheduled_at->toIso8601String();

    reminderNotificationsFor($applicant)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'interview.reminder')
        ->assertJsonPath('data.0.data.application_id', $interview->application_id)
        ->assertJsonPath('data.0.data.scheduled_at', $expectedScheduledAt);

    reminderNotificationsFor($interviewer)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'interview.reminder')
        ->assertJsonPath('data.0.data.application_id', $interview->application_id)
        ->assertJsonPath('data.0.data.scheduled_at', $expectedScheduledAt);

    // Notify-only: neither the application nor the interview moved.
    expect($interview->application()->firstOrFail()->status)->toBe(ApplicationStatus::InterviewScheduled);
    expect($interview->fresh()->completed_at)->toBeNull();
    expect($interview->fresh()->status)->toBe(InterviewStatus::PendingConfirmation);
});

it('does not remind about an interview beyond the reminder window', function () {
    // Five days out — past the default 48h window.
    [, $applicant, $interviewer] = bookedInterviewAt(now()->addHours(120));

    $this->artisan('admissions:send-interview-reminders')->assertSuccessful();

    reminderNotificationsFor($applicant)->assertOk()->assertJsonCount(0, 'data');
    reminderNotificationsFor($interviewer)->assertOk()->assertJsonCount(0, 'data');
});

it('does not remind about an interview that has already passed', function () {
    [, $applicant] = bookedInterviewAt(now()->subHours(3));

    $this->artisan('admissions:send-interview-reminders')->assertSuccessful();

    reminderNotificationsFor($applicant)->assertOk()->assertJsonCount(0, 'data');
});

it('does not remind about an already-completed interview within the window', function () {
    [$interview, $applicant] = bookedInterviewAt(now()->addHours(24));
    $interview->forceFill(['completed_at' => now()->subHour()])->save();

    $this->artisan('admissions:send-interview-reminders')->assertSuccessful();

    reminderNotificationsFor($applicant)->assertOk()->assertJsonCount(0, 'data');
});

it('respects a configured reminder window', function () {
    config()->set('admissions.interview_reminder_window_hours', 200);
    // 120h out — outside the default 48h window, but inside the configured 200h one.
    [, $applicant] = bookedInterviewAt(now()->addHours(120));

    $this->artisan('admissions:send-interview-reminders')->assertSuccessful();

    reminderNotificationsFor($applicant)->assertOk()->assertJsonCount(1, 'data');
});
