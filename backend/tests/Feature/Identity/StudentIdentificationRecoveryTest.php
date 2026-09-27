<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;
use Symfony\Component\Yaml\Yaml;

uses(RefreshDatabase::class);

it('recovers only matching accepted evidence and never overwrites a resolved admission identity', function () {
    $application = Application::factory()->interviewScheduled()->create([
        'status' => ApplicationStatus::Accepted, 'id_type' => 'passport', 'national_id' => '12345678',
    ]);
    $student = Student::factory()->create([
        'user_id' => $application->user_id, 'admission_cycle_id' => $application->admission_cycle_id,
        'national_id' => '12345678', 'identification_type' => null,
    ]);
    $this->artisan('identity:recover-identification')->assertSuccessful();
    expect(app(StudentDirectory::class)->findByStudentId($student->id)?->identity?->identificationType)->toBe('passport');
    $application->update(['id_type' => 'national_id', 'name_en' => 'Later edit']);
    $this->artisan('identity:recover-identification')->assertSuccessful();
    expect(app(StudentDirectory::class)->findByStudentId($student->id)?->identity?->identificationType)->toBe('passport')
        ->and(app(StudentDirectory::class)->findByStudentId($student->id)?->identity?->nameEn)->toBe($student->name_en);
});

it('reports missing or mismatched accepted evidence as unresolved without number-format inference', function (bool $accepted, string $number) {
    $application = Application::factory()->interviewScheduled()->create([
        'status' => $accepted ? ApplicationStatus::Accepted : ApplicationStatus::InterviewCompleted,
        'id_type' => 'national_id', 'national_id' => '29001010101010',
    ]);
    $student = Student::factory()->create([
        'user_id' => $application->user_id, 'admission_cycle_id' => $application->admission_cycle_id,
        'national_id' => $number, 'identification_type' => null,
    ]);
    $this->artisan('identity:recover-identification')->expectsOutputToContain('Unresolved: 1')->assertSuccessful();
    $owner = User::findOrFail($application->user_id);
    $owner->assignRole(Role::Student);
    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.student.identity.identification_type', null)
        ->assertJsonPath('data.student.identity.ready', false);
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.student', null);
})->with([[false, '29001010101010'], [true, '29001010101011']]);

it('documents the unresolved owner identification type as nullable in the generated API contract', function () {
    $student = Student::factory()->create(['identification_type' => null]);
    $student->user->assignRole(Role::Student);
    Sanctum::actingAs($student->user);
    $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.student.identity.identification_type', null);
    $specification = Yaml::parseFile(base_path('openapi.yaml'));
    $identity = $specification['paths']['/api/v1/me']['get']['responses'][200]['content']['application/json']['schema']['properties']['data']['properties']['student']['properties']['identity'];
    expect($identity['properties']['identification_type']['nullable'] ?? false)->toBeTrue();
});
