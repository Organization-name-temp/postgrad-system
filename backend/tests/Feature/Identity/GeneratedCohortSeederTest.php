<?php

use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GeneratedCohortSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('adds a deterministic multi-program academic cohort without changing canonical student facts', function () {
    $this->seed(DatabaseSeeder::class);
    $directory = app(StudentDirectory::class);
    $grading = app(GradingRecords::class);
    $named = $directory->find((int) User::query()->where('email', 'r02@ejust.test')->firstOrFail()->id);
    $namedCgpa = $grading->cgpaFor($named->studentId);

    $this->seed(GeneratedCohortSeeder::class);
    $users = User::query()->where('email', 'like', 'cohort.student.%@seed.ejust.test')->orderBy('email')->get();
    expect($users)->toHaveCount(180);
    $students = $users->map(fn (User $user) => $directory->find($user->id));
    // The shipped catalog contains five Programs; use all of them, not invented Programs.
    expect($students->pluck('programId')->unique()->count())->toBe(5)
        ->and($students->pluck('enrollmentTerm')->unique()->count())->toBe(5);
    $cgpas = $students->map(fn ($student) => $grading->cgpaFor($student->studentId));
    expect($cgpas->filter(fn ($value) => $value !== null)->unique()->count())->toBeGreaterThanOrEqual(6)
        ->and($cgpas->contains(fn ($value) => $value !== null && $value < 3))->toBeTrue()
        ->and($cgpas->contains(null))->toBeTrue();

    $identities = $students->map(fn ($student) => $student->toArray())->all();
    $this->seed(GeneratedCohortSeeder::class);
    expect(User::query()->where('email', 'like', 'cohort.student.%@seed.ejust.test')->count())->toBe(180)
        ->and($users->map(fn (User $user) => $directory->find($user->id)->toArray())->all())->toBe($identities)
        ->and($directory->find($named->userId)->toArray())->toBe($named->toArray())
        ->and($grading->cgpaFor($named->studentId))->toBe($namedCgpa);
});

it('adds an idempotent screening population beyond one standard page', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(GeneratedCohortSeeder::class);
    actingAsRole(Role::StudentAffairs);
    $rows = $this->getJson('/api/v1/applications?per_page=100')->assertOk()->json('data');
    $generated = collect($rows)->filter(fn (array $row) => str_starts_with($row['name_en'], 'Cohort Applicant '));
    expect($generated)->toHaveCount(40);
    $this->seed(GeneratedCohortSeeder::class);
    $again = $this->getJson('/api/v1/applications?per_page=100')->assertOk()->json('data');
    expect(collect($again)->filter(fn (array $row) => str_starts_with($row['name_en'], 'Cohort Applicant '))->count())->toBe(40);
});
