<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Enums\PreviousDegree;
use Modules\Admissions\Internal\Models\Application;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

/*
| Program selection (ticket 06) — the applicant picks one primary and an optional
| alternative Program, each validated (through the Catalog\Public ProgramCatalog
| facade, no cross-module FK — ADR-0012) to exist, to match the degree applied for,
| and against the degree-qualification rule. Driven through the /api/v1 HTTP seam:
| observable behaviour only — status codes, JSON bodies, the stored refs.
*/

/**
 * A signed-in applicant on an EmailVerified application (the program step's state),
 * with a complete Step-1 snapshot whose previous degree and applied-for degree are
 * overridable per case. Returns the Application.
 *
 * @param  array<string, mixed>  $overrides
 */
function programStepApplicant(array $overrides = []): Application
{
    $user = User::factory()->create();
    $application = Application::factory()->withPersonalInfo()->create(array_merge([
        'user_id' => $user->id,
        'status' => ApplicationStatus::EmailVerified,
    ], $overrides));
    Sanctum::actingAs($user);

    return $application;
}

it('stores a qualifying primary program that matches the degree applied for', function () {
    $application = programStepApplicant(['previous_degree' => PreviousDegree::BSc, 'applying_for_degree' => 'MSc']);
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);

    test()->patchJson('/api/v1/application/programs', ['primary_program_id' => $program->id])
        ->assertOk()
        ->assertJsonPath('data.primary_program_id', $program->id)
        ->assertJsonPath('data.alternative_program_id', null)
        ->assertJsonPath('data.primary_program.code', $program->code)
        ->assertJsonPath('data.primary_program.degree_level', 'MSc');

    $this->assertDatabaseHas('applications', [
        'id' => $application->id,
        'primary_program_id' => $program->id,
    ]);
});

it('stores an optional alternative program alongside the primary', function () {
    programStepApplicant(['previous_degree' => PreviousDegree::MSc, 'applying_for_degree' => 'PhD']);
    $primary = Program::factory()->create(['degree_level' => DegreeLevel::PhD]);
    $alternative = Program::factory()->create(['degree_level' => DegreeLevel::PhD]);

    test()->patchJson('/api/v1/application/programs', [
        'primary_program_id' => $primary->id,
        'alternative_program_id' => $alternative->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.primary_program_id', $primary->id)
        ->assertJsonPath('data.alternative_program_id', $alternative->id)
        ->assertJsonPath('data.alternative_program.id', $alternative->id);
});

it('requires authentication to select a program', function () {
    $program = Program::factory()->create();

    test()->patchJson('/api/v1/application/programs', ['primary_program_id' => $program->id])
        ->assertUnauthorized();
});

it('requires a primary program', function () {
    programStepApplicant();

    test()->patchJson('/api/v1/application/programs', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('primary_program_id');
});

it('rejects a primary program id that does not exist', function () {
    programStepApplicant();

    test()->patchJson('/api/v1/application/programs', ['primary_program_id' => 999_999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('primary_program_id');
});

it('rejects a primary program whose level does not match the degree applied for', function () {
    programStepApplicant(['previous_degree' => PreviousDegree::MSc, 'applying_for_degree' => 'MSc']);
    // Applying for an MSc but pointing at a PhD program.
    $program = Program::factory()->create(['degree_level' => DegreeLevel::PhD]);

    test()->patchJson('/api/v1/application/programs', ['primary_program_id' => $program->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('primary_program_id');
});

it('rejects a program the applicant’s previous degree does not qualify them for', function () {
    // A BSc holder applying for a PhD: the level matches applying_for_degree but the
    // qualification rule refuses BSc → PhD (two steps up).
    programStepApplicant(['previous_degree' => PreviousDegree::BSc, 'applying_for_degree' => 'PhD']);
    $program = Program::factory()->create(['degree_level' => DegreeLevel::PhD]);

    test()->patchJson('/api/v1/application/programs', ['primary_program_id' => $program->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('primary_program_id');
});

it('rejects an unqualified alternative program', function () {
    programStepApplicant(['previous_degree' => PreviousDegree::BSc, 'applying_for_degree' => 'MSc']);
    $primary = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);
    // The alternative must qualify too; a PhD is two steps above a BSc.
    $alternative = Program::factory()->create(['degree_level' => DegreeLevel::PhD]);

    test()->patchJson('/api/v1/application/programs', [
        'primary_program_id' => $primary->id,
        'alternative_program_id' => $alternative->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('alternative_program_id');
});

it('rejects an alternative that is the same as the primary', function () {
    programStepApplicant(['previous_degree' => PreviousDegree::BSc, 'applying_for_degree' => 'MSc']);
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);

    test()->patchJson('/api/v1/application/programs', [
        'primary_program_id' => $program->id,
        'alternative_program_id' => $program->id,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('alternative_program_id');
});

it('refuses program selection once the application has left the applicant’s hands', function () {
    programStepApplicant(['status' => ApplicationStatus::UnderSAReview]);
    $program = Program::factory()->create(['degree_level' => DegreeLevel::MSc]);

    test()->patchJson('/api/v1/application/programs', ['primary_program_id' => $program->id])
        ->assertStatus(409);
});
