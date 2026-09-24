<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

function creditSummaryStudent(): Student
{
    $program = Program::factory()->create();
    $version = CatalogVersion::query()->create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => [
            'prep_required_ch' => 6,
            'core_required_ch' => 6,
            'elective_required_ch' => 9,
            'curriculum' => [],
        ],
        'changed_at' => now(),
    ]);
    $advisor = User::factory()->create();
    $advisor->assignRole(Role::Advisor);
    $student = Student::factory()->create([
        'program_id' => $program->id,
        'curriculum_version_id' => $version->id,
        'advisor_id' => $advisor->id,
    ]);
    $student->user->assignRole(Role::Student);

    return $student;
}

it('returns the owner-composed summary and card for the acting Student', function () {
    $student = creditSummaryStudent();
    Sanctum::actingAs($student->user);

    $this->getJson('/api/v1/academic-standing/me/credit-hours')
        ->assertOk()
        ->assertJsonPath('data.total.baseline_required_ch', 21)
        ->assertJsonPath('data.total.adjusted_required_ch', 21)
        ->assertJsonPath('data.total.satisfied_ch', 0)
        ->assertJsonPath('data.total.remaining_ch', 21)
        ->assertJsonCount(3, 'data.categories');
    $this->getJson('/api/v1/academic-standing/cards/credit-hours')
        ->assertOk()
        ->assertJsonPath('data.remaining_ch', 21);
});

it('allows only the current Advisor or authorized oversight to read another Student', function () {
    $student = creditSummaryStudent();
    Sanctum::actingAs(User::query()->findOrFail($student->advisor_id));
    $this->getJson("/api/v1/academic-standing/students/{$student->id}/credit-hours")->assertOk();

    $otherAdvisor = User::factory()->create();
    $otherAdvisor->assignRole(Role::Advisor);
    Sanctum::actingAs($otherAdvisor);
    $this->getJson("/api/v1/academic-standing/students/{$student->id}/credit-hours")->assertForbidden();

    $studentAffairs = User::factory()->create();
    $studentAffairs->assignRole(Role::StudentAffairs);
    Sanctum::actingAs($studentAffairs);
    $this->getJson("/api/v1/academic-standing/students/{$student->id}/credit-hours")->assertOk();
});
