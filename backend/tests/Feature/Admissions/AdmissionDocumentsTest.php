<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Admissions\Public\AdmissionDocuments;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

it('publishes only the requested Applicants admission documents through the Public seam', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $owner->id]);
    $otherApplication = Application::factory()->create(['user_id' => $other->id]);
    $transcript = ApplicationDocument::factory()->for($application)->create([
        'document_type' => 'transcript',
        'original_filename' => 'owned-transcript.pdf',
    ]);
    ApplicationDocument::factory()->for($otherApplication)->create([
        'document_type' => 'transcript',
        'original_filename' => 'other-transcript.pdf',
    ]);

    $documents = app(AdmissionDocuments::class)->forApplicant($owner->id);

    expect($documents)->toHaveCount(1)
        ->and($documents[0]->id)->toBe($transcript->ulid)
        ->and($documents[0]->type)->toBe('transcript')
        ->and($documents[0]->originalFilename)->toBe('owned-transcript.pdf');
});
