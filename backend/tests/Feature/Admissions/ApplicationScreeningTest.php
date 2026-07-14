<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Application status machine + Student Affairs screening + status timeline (ticket
| 07). Driven end-to-end through the /api/v1 HTTP seam — status codes, JSON bodies,
| the timeline via its endpoint, and notification rows via the notifications endpoint
| (spec §Testing Decisions) — never by reaching into the state machine's internals.
*/

/**
 * A finalized application resting in Student Affairs review, owned by a fresh
 * applicant, with a chosen primary Program. Returns [Application, applicant User].
 *
 * @param  array<string, mixed>  $overrides
 * @return array{0: Application, 1: User}
 */
function underReviewApplication(array $overrides = []): array
{
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $application = Application::factory()->underReview()->create(array_merge([
        'primary_program_id' => $program->id,
    ], $overrides));

    return [$application, User::query()->findOrFail($application->user_id)];
}

/**
 * Attach every document an application owes for review — the four always-required
 * documents plus the English certificate (the withPersonalInfo snapshot reports an
 * IELTS test) — so a resubmit passes the completeness re-check.
 */
function attachRequiredDocumentsForReview(Application $application): void
{
    foreach ([
        DocumentType::GraduationCertificate,
        DocumentType::Transcript,
        DocumentType::NationalId,
        DocumentType::Photo,
        DocumentType::EnglishCertificate,
    ] as $type) {
        ApplicationDocument::factory()->ofType($type)->create(['application_id' => $application->id]);
    }
}

it('lists the Student Affairs screening queue with a derived age', function () {
    [$application] = underReviewApplication();
    // The application entered review four days ago — the queue derives that age.
    ApplicationStatusTransition::factory()
        ->enteredAt(ApplicationStatus::UnderSAReview, Carbon::now()->subDays(4))
        ->create(['application_id' => $application->id]);
    // A draft never reaches the queue.
    Application::factory()->create(['status' => ApplicationStatus::Draft]);

    actingAsRole(Role::StudentAffairs);

    $this->getJson('/api/v1/applications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $application->id)
        ->assertJsonPath('data.0.status', 'under_sa_review')
        ->assertJsonPath('data.0.age_days', 4)
        ->assertJsonPath('data.0.primary_program.degree_level', 'MSc');
});

it('refuses the queue to a user without application.screen', function () {
    actingAsRole(Role::Applicant); // holds application.submit, not application.screen

    $this->getJson('/api/v1/applications')->assertForbidden();
});

it('lets a reviewer open an application with its documents', function () {
    [$application] = underReviewApplication();
    actingAsRole(Role::StudentAffairs);

    $this->getJson("/api/v1/applications/{$application->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $application->id)
        ->assertJsonPath('data.status', 'under_sa_review')
        ->assertJsonStructure(['data' => ['id', 'status', 'name_en', 'documents']]);
});

it('approves an application, auto-forwarding it through sa_approved to under_dean_review', function () {
    [$application] = underReviewApplication();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/screen", ['action' => 'approve'])
        ->assertOk()
        ->assertJsonPath('data.status', 'under_dean_review');

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderDeanReview);

    // Both the SA approval and the automatic forward are on the append-only timeline.
    $toStatuses = collect($this->getJson("/api/v1/applications/{$application->id}/timeline")->json('data'))
        ->pluck('to_status');
    expect($toStatuses)->toContain('sa_approved')->toContain('under_dean_review');
});

it('returns an application for revision with instructions and notifies the applicant', function () {
    [$application, $applicant] = underReviewApplication();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/screen", [
        'action' => 'return',
        'note' => 'Please re-upload a clearer transcript.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'returned_for_revision');

    // The timeline records the move and the reviewer's note.
    $this->getJson("/api/v1/applications/{$application->id}/timeline")
        ->assertOk()
        ->assertJsonPath('data.0.to_status', 'returned_for_revision')
        ->assertJsonPath('data.0.note', 'Please re-upload a clearer transcript.');

    // The applicant has a notification about the return.
    Sanctum::actingAs($applicant);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'application.returned');
});

it('rejects an application with a reason and notifies the applicant', function () {
    [$application, $applicant] = underReviewApplication();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/screen", [
        'action' => 'reject',
        'note' => 'Does not meet the minimum GPA.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    Sanctum::actingAs($applicant);
    $this->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'decision.recorded');
});

it('requires a note to return or reject', function () {
    [$application] = underReviewApplication();
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/screen", ['action' => 'return'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');

    $this->postJson("/api/v1/applications/{$application->id}/screen", ['action' => 'reject'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('note');
});

it('refuses to screen an application that is not under SA review (409)', function () {
    // Already forwarded to the Dean — SA can no longer approve it.
    [$application] = underReviewApplication(['status' => ApplicationStatus::UnderDeanReview]);
    actingAsRole(Role::StudentAffairs);

    $this->postJson("/api/v1/applications/{$application->id}/screen", ['action' => 'approve'])
        ->assertStatus(409);

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderDeanReview);
});

it('refuses the screen action to a user without application.screen', function () {
    [$application] = underReviewApplication();
    actingAsRole(Role::Applicant);

    $this->postJson("/api/v1/applications/{$application->id}/screen", ['action' => 'approve'])
        ->assertForbidden();
});

it('loops a returned application back into review when the applicant resubmits', function () {
    [$application, $applicant] = underReviewApplication();
    // A complete application (as it was when it first reached review).
    attachRequiredDocumentsForReview($application);
    actingAsRole(Role::StudentAffairs);
    $this->postJson("/api/v1/applications/{$application->id}/screen", [
        'action' => 'return',
        'note' => 'Fix your documents.',
    ])->assertOk();

    // The applicant addresses the note and resubmits.
    Sanctum::actingAs($applicant);
    $this->postJson('/api/v1/application/resubmit')
        ->assertOk()
        ->assertJsonPath('data.status', 'under_sa_review');

    expect($application->fresh()->status)->toBe(ApplicationStatus::UnderSAReview);
});

it('refuses to resubmit an application that was not returned (409)', function () {
    [$application, $applicant] = underReviewApplication();
    Sanctum::actingAs($applicant);

    // Still under review, never returned — there is nothing to resubmit.
    $this->postJson('/api/v1/application/resubmit')->assertStatus(409);
});

it('refuses to resubmit an incomplete application (422)', function () {
    // Returned, then the applicant removed a required document before resubmitting:
    // an incomplete application must not slip back into review (story 20).
    [$application, $applicant] = underReviewApplication(['status' => ApplicationStatus::ReturnedForRevision]);
    // No documents attached — the required set is missing.
    Sanctum::actingAs($applicant);

    $this->postJson('/api/v1/application/resubmit')
        ->assertStatus(422)
        ->assertJsonValidationErrors('documents');

    expect($application->fresh()->status)->toBe(ApplicationStatus::ReturnedForRevision);
});

it('lets the owning applicant read their own timeline', function () {
    [$application, $applicant] = underReviewApplication();
    ApplicationStatusTransition::factory()->create(['application_id' => $application->id]);
    Sanctum::actingAs($applicant);

    $this->getJson("/api/v1/applications/{$application->id}/timeline")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.to_status', 'under_sa_review');
});

it('forbids a stranger from reading another applicant’s timeline', function () {
    [$application] = underReviewApplication();
    // A different applicant, neither the owner nor a reviewer.
    actingAsRole(Role::Applicant);

    $this->getJson("/api/v1/applications/{$application->id}/timeline")->assertForbidden();
});
