<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\Phase7AcademicReportSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Public\TerminationCases;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('seeds isolated Report Cases from owner-derived two-course D/F evidence and preserves them on reseed', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(Phase7AcademicReportSeeder::class);
    $users = User::query()->where('email', 'like', 'phase7.report.case.%@seed.ejust.test')->pluck('id');
    $ids = Student::query()->whereIn('user_id', $users)->orderBy('id')->pluck('id')->all();
    expect($ids)->toHaveCount(3);
    $cases = app(TerminationCases::class)->currentForStudents($ids);
    $attempts = app(GradingRecords::class)->effectiveAttemptsForStudents($ids);
    expect($cases)->toHaveCount(3);
    foreach ($ids as $id) {
        expect($cases[$id]->state)->toBe('AwaitingAdvisor')
            ->and(array_column($attempts[$id], 'letterGrade'))->toBe(['D', 'F'])
            ->and(count(array_unique(array_column($cases[$id]->evidence['df_grades'], 'course_id'))))->toBe(2);
    }
    $this->seed(Phase7AcademicReportSeeder::class);
    expect(app(TerminationCases::class)->currentForStudents($ids))->toEqual($cases);
    $actor = User::query()->where('email', 'r05@ejust.test')->firstOrFail();
    expect($actor->hasRole(Role::StudentAffairs))->toBeTrue();
    Sanctum::actingAs($actor);
    $this->getJson('/api/v1/academic-standing/reports/termination?q=Phase%207%20report')->assertOk()
        ->assertJsonPath('data.case_count', 3)->assertJsonPath('data.student_count', 3)->assertJsonPath('data.states.AwaitingAdvisor', 3);
});
