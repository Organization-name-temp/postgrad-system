<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\DocumentType;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/*
| Document upload (ticket 05) — the applicant uploads certificates and files, each
| validated against its DocumentType's own format/size/cardinality rules; files
| land on the private `local` disk under an opaque ULID and are streamed only to
| the owner or a reviewer (application.screen). Editable while the application is in
| the applicant's hands; locked once it enters review. Driven through the /api/v1
| HTTP seam: observable behaviour only (status codes, JSON, the stored file, the
| streamed response) — never the model or storage internals. Shared helpers
| (actingAsRole) live in tests/Pest.php.
*/

// Every case uploads to a fake private disk, so nothing touches real storage.
beforeEach(fn () => Storage::fake('local'));

/**
 * A fresh applicant owning one application in a document-editable status, signed
 * in. Returns [User, Application].
 *
 * @return array{0: User, 1: Application}
 */
function editableApplicant(ApplicationStatus $status = ApplicationStatus::EmailVerified): array
{
    $user = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $user->id, 'status' => $status]);
    Sanctum::actingAs($user);

    return [$user, $application];
}

/** Upload a document as the current applicant and assert it succeeded; returns the response. */
function uploadDocument(DocumentType $type, ?UploadedFile $file = null)
{
    $file ??= UploadedFile::fake()->create('document.pdf', 50, 'application/pdf');

    return test()->postJson('/api/v1/application/documents', [
        'document_type' => $type->value,
        'file' => $file,
    ])->assertCreated();
}

it('stores a valid document on the private disk under an opaque ULID', function () {
    [, $application] = editableApplicant();

    $response = test()->postJson('/api/v1/application/documents', [
        'document_type' => 'graduation_certificate',
        'file' => UploadedFile::fake()->create('certificate.pdf', 120, 'application/pdf'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.document_type', 'graduation_certificate')
        ->assertJsonPath('data.original_filename', 'certificate.pdf')
        ->assertJsonPath('data.mime', 'application/pdf');

    $ulid = $response->json('data.id');
    // The public identifier is the ULID, and it (not a row id/filename) is the URL handle.
    expect($ulid)->toBeString()->toHaveLength(26);
    expect($response->json('data.download_url'))->toContain($ulid);

    $document = ApplicationDocument::firstWhere('ulid', $ulid);
    expect($document->application_id)->toBe($application->id);
    Storage::disk('local')->assertExists($document->stored_path);
});

it('requires authentication to upload a document', function () {
    test()->postJson('/api/v1/application/documents', [
        'document_type' => 'graduation_certificate',
        'file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
    ])->assertUnauthorized();
});

it('rejects an unknown document type', function () {
    editableApplicant();

    test()->postJson('/api/v1/application/documents', [
        'document_type' => 'passport_selfie',
        'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('document_type');
});

it('rejects a file whose format the type does not allow', function () {
    editableApplicant();

    // The personal photo accepts images only; a PDF is refused.
    test()->postJson('/api/v1/application/documents', [
        'document_type' => 'photo',
        'file' => UploadedFile::fake()->create('scan.pdf', 100, 'application/pdf'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('rejects a file over the type’s size limit', function () {
    editableApplicant();

    // The photo cap is 5 MB; a 6 MB image is refused.
    test()->postJson('/api/v1/application/documents', [
        'document_type' => 'photo',
        'file' => UploadedFile::fake()->create('huge.jpg', 6 * 1024, 'image/jpeg'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});

it('caps a single-slot type and allows a replacement once the old one is removed', function () {
    editableApplicant();

    $first = uploadDocument(DocumentType::GraduationCertificate)->json('data.id');

    // A second graduation certificate is one too many (cardinality 1).
    test()->postJson('/api/v1/application/documents', [
        'document_type' => 'graduation_certificate',
        'file' => UploadedFile::fake()->create('again.pdf', 50, 'application/pdf'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('document_type');

    // Removing the first frees the slot, so a replacement is accepted (the "replace" flow).
    test()->deleteJson("/api/v1/application/documents/{$first}")->assertNoContent();
    uploadDocument(DocumentType::GraduationCertificate, UploadedFile::fake()->create('replacement.pdf', 50, 'application/pdf'));
});

it('allows up to two recommendation letters and refuses a third', function () {
    editableApplicant();

    uploadDocument(DocumentType::RecommendationLetter, UploadedFile::fake()->create('r1.pdf', 20, 'application/pdf'));
    uploadDocument(DocumentType::RecommendationLetter, UploadedFile::fake()->create('r2.pdf', 20, 'application/pdf'));

    test()->postJson('/api/v1/application/documents', [
        'document_type' => 'recommendation_letter',
        'file' => UploadedFile::fake()->create('r3.pdf', 20, 'application/pdf'),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('document_type');
});

it('lists the uploaded documents on the application resource', function () {
    editableApplicant();
    uploadDocument(DocumentType::Transcript, UploadedFile::fake()->create('transcript.pdf', 60, 'application/pdf'));

    test()->getJson('/api/v1/application')
        ->assertOk()
        ->assertJsonPath('data.documents.0.document_type', 'transcript')
        ->assertJsonPath('data.documents.0.original_filename', 'transcript.pdf')
        ->assertJsonStructure([
            'data' => ['documents' => [['id', 'document_type', 'mime', 'size_bytes', 'uploaded_at', 'download_url']]],
        ]);
});

it('removes a document, deleting the file and freeing its slot', function () {
    [, $application] = editableApplicant();
    $ulid = uploadDocument(DocumentType::GraduationCertificate)->json('data.id');

    $document = ApplicationDocument::firstWhere('ulid', $ulid);
    Storage::disk('local')->assertExists($document->stored_path);

    test()->deleteJson("/api/v1/application/documents/{$ulid}")->assertNoContent();

    Storage::disk('local')->assertMissing($document->stored_path);
    $this->assertDatabaseMissing('application_documents', ['ulid' => $ulid]);
    expect($application->documents()->count())->toBe(0);
});

it('does not let an applicant remove another applicant’s document', function () {
    editableApplicant();
    $ulid = uploadDocument(DocumentType::GraduationCertificate)->json('data.id');

    editableApplicant(); // now signed in as a different applicant

    test()->deleteJson("/api/v1/application/documents/{$ulid}")->assertNotFound();
    $this->assertDatabaseHas('application_documents', ['ulid' => $ulid]);
});

it('blocks uploading once the application has left the applicant’s hands', function () {
    editableApplicant(ApplicationStatus::UnderSAReview);

    test()->postJson('/api/v1/application/documents', [
        'document_type' => 'graduation_certificate',
        'file' => UploadedFile::fake()->create('c.pdf', 50, 'application/pdf'),
    ])->assertStatus(409);
});

it('blocks removing a document once the application is under review', function () {
    [, $application] = editableApplicant(ApplicationStatus::UnderSAReview);
    $document = ApplicationDocument::factory()->create(['application_id' => $application->id]);

    test()->deleteJson("/api/v1/application/documents/{$document->ulid}")->assertStatus(409);
    $this->assertDatabaseHas('application_documents', ['ulid' => $document->ulid]);
});

it('streams a document back to its owner', function () {
    editableApplicant();
    $url = uploadDocument(DocumentType::GraduationCertificate)->json('data.download_url');

    test()->get($url)->assertOk();
});

it('streams a document to a reviewer holding application.screen', function () {
    editableApplicant();
    $url = uploadDocument(DocumentType::GraduationCertificate)->json('data.download_url');

    actingAsRole(Role::StudentAffairs);

    test()->get($url)->assertOk();
});

it('refuses to stream a document to a non-owner without application.screen', function () {
    editableApplicant();
    $url = uploadDocument(DocumentType::GraduationCertificate)->json('data.download_url');

    editableApplicant(); // a different applicant, not a reviewer

    test()->get($url)->assertForbidden();
});

it('requires authentication to stream a document', function () {
    $document = ApplicationDocument::factory()->create();

    test()->getJson(route('admissions.documents.download', ['document' => $document->ulid]))
        ->assertUnauthorized();
});
