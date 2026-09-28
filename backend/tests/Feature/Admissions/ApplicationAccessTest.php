<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationDocument;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

/** @return array{User, Application, ApplicationDocument} */
function applicationAccessFixture(bool $insideFaculty = false): array
{
    $inside = Program::factory()->create();
    $outside = Program::factory()->create();
    $dean = User::factory()->create();
    $dean->assignRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $dean->id, 'faculty_id' => $inside->department->faculty_id]);
    $application = Application::factory()->underReview()->create(['primary_program_id' => $insideFaculty ? $inside->id : $outside->id]);
    $document = ApplicationDocument::factory()->create(['application_id' => $application->id]);
    Storage::disk('local')->put($document->stored_path, '%PDF-1.4 PRIVATE');
    Sanctum::actingAs($dean);

    return [$dean, $application, $document];
}

it('denies outside faculty detail timeline and document reads even when the identifiers are known', function () {
    [, $application, $document] = applicationAccessFixture();
    $this->getJson('/api/v1/applications')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/applications/'.$application->id)->assertForbidden();
    $this->getJson('/api/v1/applications/'.$application->id.'/timeline')->assertForbidden();
    $this->get('/api/v1/documents/'.$document->ulid)->assertForbidden();
});

it('denies every outside faculty screening action without transitions or notifications', function (string $action) {
    [, $application] = applicationAccessFixture();
    $this->postJson('/api/v1/applications/'.$application->id.'/screen', ['action' => $action, 'note' => 'Review note'])
        ->assertForbidden();
    $this->assertDatabaseHas('applications', ['id' => $application->id, 'status' => 'under_sa_review']);
    $this->assertDatabaseCount('application_status_transitions', 0);
    $this->assertDatabaseCount('notifications', 0);
})->with(['approve', 'return', 'reject']);

it('denies a dean with no assignment or an unresolved program', function (string $missing) {
    [$dean, $application, $document] = applicationAccessFixture(true);
    if ($missing === 'assignment') {
        FacultyDeanAssignment::query()->where('user_id', $dean->id)->delete();
    } else {
        $application->update(['primary_program_id' => $missing === 'null' ? null : 999999]);
    }
    $this->getJson('/api/v1/applications/'.$application->id)->assertForbidden();
    $this->get('/api/v1/documents/'.$document->ulid)->assertForbidden();
    $this->postJson('/api/v1/applications/'.$application->id.'/screen', ['action' => 'reject', 'note' => 'Review note'])->assertForbidden();
})->with(['assignment', 'null', 'missing-program']);

it('keeps in faculty dean access and screening', function () {
    [, $application, $document] = applicationAccessFixture(true);
    $this->getJson('/api/v1/applications')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/applications/'.$application->id)->assertOk();
    $this->getJson('/api/v1/applications/'.$application->id.'/timeline')->assertOk();
    $this->get('/api/v1/documents/'.$document->ulid)->assertOk()->assertStreamedContent('%PDF-1.4 PRIVATE');
    $this->postJson('/api/v1/applications/'.$application->id.'/screen', ['action' => 'approve'])
        ->assertOk()->assertJsonPath('data.status', 'under_dean_review');
});

it('retains university wide reads and screening for SA administrators and multi role users', function (Role $role) {
    [$user, $application, $document] = applicationAccessFixture();
    $user->assignRole($role);
    Sanctum::actingAs($user->fresh());
    $this->getJson('/api/v1/applications')->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/applications/'.$application->id)->assertOk();
    $this->getJson('/api/v1/applications/'.$application->id.'/timeline')->assertOk();
    $this->get('/api/v1/documents/'.$document->ulid)->assertOk();
    $this->postJson('/api/v1/applications/'.$application->id.'/screen', ['action' => 'reject', 'note' => 'Review note'])
        ->assertOk()->assertJsonPath('data.status', 'rejected');
})->with([Role::StudentAffairs, Role::SystemAdmin, Role::SuperAdmin]);

it('keeps owner file and timeline access without giving the owner screening authority', function () {
    [, $application, $document] = applicationAccessFixture();
    $owner = User::findOrFail($application->user_id);
    $owner->assignRole(Role::Applicant);
    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/applications/'.$application->id.'/timeline')->assertOk();
    $this->get('/api/v1/documents/'.$document->ulid)->assertOk();
    $this->postJson('/api/v1/applications/'.$application->id.'/screen', ['action' => 'approve'])->assertForbidden();
});
