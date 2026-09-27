<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Public\CreditHourCategory;
use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\AcademicStanding\Public\GraduationBlocker;
use Modules\AcademicStanding\Public\GraduationProgress;
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
    $this->getJson('/api/v1/academic-standing/me/graduation-readiness')
        ->assertOk()
        ->assertJsonPath('data.ready', false)
        ->assertJsonPath('data.remaining_credit_hours', 21)
        ->assertJsonPath('data.cgpa', null)
        ->assertJsonPath('data.approaching_graduation', false)
        ->assertJsonPath('data.blockers.0', 'RemainingRequirements')
        ->assertJsonPath('data.blockers.1', 'CgpaUnavailable');
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

it('reads credit summaries in bounded owner batches with single-Student parity', function () {
    $first = creditSummaryStudent();
    $others = Student::factory()->count(104)->create([
        'program_id' => $first->program_id,
        'curriculum_version_id' => $first->curriculum_version_id,
        'advisor_id' => $first->advisor_id,
    ]);
    $ids = [$first->id, ...$others->modelKeys()];
    $reader = app(CreditHourSummaries::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $batch = $reader->forStudents([...$ids, $first->id, 999999]);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($batch)->toHaveCount(105)
        ->and($batch[$first->id]->rows[CreditHourCategory::Total->value]->remainingCh)->toBe(21)
        ->and($queries)->toBeLessThan(100);
    foreach ($ids as $id) {
        expect($batch[$id])->toEqual($reader->forStudent($id));
    }
});

it('bulk derives advisory readiness without supplying a hypothetical completing schedule', function () {
    $student = creditSummaryStudent();
    $version = CatalogVersion::query()->findOrFail($student->curriculum_version_id);
    $version->update(['snapshot' => ['prep_required_ch' => 0, 'core_required_ch' => 3, 'elective_required_ch' => 0, 'curriculum' => []]]);
    $missing = Student::factory()->create(['curriculum_version_id' => 999999]);
    $reader = app(GraduationProgress::class);
    $batch = $reader->readinessForStudents([$student->id, $student->id, $missing->id]);
    expect($batch)->toHaveCount(1)
        ->and($batch[$student->id]->remainingCreditHours)->toBe(3)
        ->and($batch[$student->id]->cgpa)->toBeNull()
        ->and($batch[$student->id]->ready)->toBeFalse()
        ->and($batch[$student->id]->approachingGraduation)->toBeFalse()
        ->and($batch[$student->id]->blockers)->toBe([
            GraduationBlocker::RemainingRequirements,
            GraduationBlocker::CgpaUnavailable,
        ])
        ->and($batch[$student->id])->toEqual($reader->readinessForStudent($student->id))
        ->and($reader->readinessForStudents([]))->toBe([]);
});
