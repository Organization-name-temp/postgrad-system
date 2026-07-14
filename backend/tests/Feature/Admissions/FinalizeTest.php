<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Enums\EnglishTest;
use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/*
| Finalize (ticket 06) — the last wizard step moves a complete application from
| EmailVerified into Student Affairs review. "Complete" means a primary Program is
| chosen and every required and conditionally-required document is uploaded; a
| missing prerequisite is a 422, and finalizing anything not awaiting finalize is a
| 409. Driven through the /api/v1 HTTP seam: observable behaviour only.
*/

/** The always-required document types (before conditionals). */
const REQUIRED_DOCUMENT_TYPES = [
    DocumentType::GraduationCertificate,
    DocumentType::Transcript,
    DocumentType::NationalId,
    DocumentType::Photo,
];

/**
 * Attach one document of each given type to the application.
 *
 * @param  list<DocumentType>  $types
 */
function attachDocuments(Application $application, array $types): void
{
    foreach ($types as $type) {
        ApplicationDocument::factory()->ofType($type)->create(['application_id' => $application->id]);
    }
}

/**
 * A signed-in applicant on an EmailVerified application with a chosen primary
 * Program, its Step-1 snapshot complete. Overridable per case. Returns the Application.
 *
 * @param  array<string, mixed>  $overrides
 */
function finalizableApplicant(array $overrides = []): Application
{
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    $user = User::factory()->create();
    $application = Application::factory()->withPersonalInfo()->create(array_merge([
        'user_id' => $user->id,
        'status' => ApplicationStatus::EmailVerified,
        'primary_program_id' => $program->id,
    ], $overrides));
    Sanctum::actingAs($user);

    return $application;
}

it('finalizes a complete application, moving it to under_sa_review and stamping finalized_at', function () {
    // withPersonalInfo() reports an IELTS test, so the English certificate is owed too.
    $application = finalizableApplicant();
    attachDocuments($application, [...REQUIRED_DOCUMENT_TYPES, DocumentType::EnglishCertificate]);

    test()->postJson('/api/v1/application/finalize')
        ->assertOk()
        ->assertJsonPath('data.status', 'under_sa_review');

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::UnderSAReview);
    expect($application->finalized_at)->not->toBeNull();
});

it('requires authentication to finalize', function () {
    test()->postJson('/api/v1/application/finalize')->assertUnauthorized();
});

it('blocks finalize when no primary program is chosen', function () {
    $application = finalizableApplicant(['primary_program_id' => null]);
    attachDocuments($application, [...REQUIRED_DOCUMENT_TYPES, DocumentType::EnglishCertificate]);

    test()->postJson('/api/v1/application/finalize')
        ->assertStatus(422)
        ->assertJsonValidationErrors('primary_program_id');

    expect($application->fresh()->status)->toBe(ApplicationStatus::EmailVerified);
});

it('blocks finalize when a required document is missing', function () {
    $application = finalizableApplicant();
    // Only three of the four always-required documents.
    attachDocuments($application, [DocumentType::GraduationCertificate, DocumentType::Transcript, DocumentType::NationalId]);

    test()->postJson('/api/v1/application/finalize')
        ->assertStatus(422)
        ->assertJsonValidationErrors('documents');
});

it('blocks finalize when the conditionally-required English certificate is missing', function () {
    // An IELTS test was reported (withPersonalInfo default) but no certificate uploaded.
    $application = finalizableApplicant();
    attachDocuments($application, REQUIRED_DOCUMENT_TYPES);

    test()->postJson('/api/v1/application/finalize')
        ->assertStatus(422)
        ->assertJsonValidationErrors('documents');
});

it('does not require an English certificate when no test was reported', function () {
    $application = finalizableApplicant(['english_test' => EnglishTest::None, 'english_score' => null]);
    attachDocuments($application, REQUIRED_DOCUMENT_TYPES);

    test()->postJson('/api/v1/application/finalize')
        ->assertOk()
        ->assertJsonPath('data.status', 'under_sa_review');
});

it('requires a PhD applicant to have uploaded their MSc thesis', function () {
    $program = Program::factory()->create(['degree_level' => DegreeLevel::PhD]);
    $application = finalizableApplicant([
        'previous_degree' => PreviousDegree::MSc,
        'applying_for_degree' => 'PhD',
        'english_test' => EnglishTest::None,
        'english_score' => null,
        'primary_program_id' => $program->id,
    ]);
    // All the always-required docs, but no MSc thesis.
    attachDocuments($application, REQUIRED_DOCUMENT_TYPES);

    test()->postJson('/api/v1/application/finalize')
        ->assertStatus(422)
        ->assertJsonValidationErrors('documents');

    // Adding the thesis completes it.
    attachDocuments($application, [DocumentType::MscThesis]);

    test()->postJson('/api/v1/application/finalize')
        ->assertOk()
        ->assertJsonPath('data.status', 'under_sa_review');
});

it('refuses to finalize an application that is not awaiting finalize (409)', function () {
    // A Draft has not verified its email, let alone reached finalize.
    $application = finalizableApplicant(['status' => ApplicationStatus::Draft]);
    attachDocuments($application, [...REQUIRED_DOCUMENT_TYPES, DocumentType::EnglishCertificate]);

    test()->postJson('/api/v1/application/finalize')->assertStatus(409);

    expect($application->fresh()->status)->toBe(ApplicationStatus::Draft);
});

it('refuses to finalize an application already under review (409)', function () {
    $application = finalizableApplicant(['status' => ApplicationStatus::UnderSAReview]);
    attachDocuments($application, [...REQUIRED_DOCUMENT_TYPES, DocumentType::EnglishCertificate]);

    test()->postJson('/api/v1/application/finalize')->assertStatus(409);
});
