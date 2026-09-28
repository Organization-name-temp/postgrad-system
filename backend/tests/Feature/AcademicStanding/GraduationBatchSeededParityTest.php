<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AcademicStanding\Public\CreditHourSummaries;
use Modules\AcademicStanding\Public\GraduationProgress;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

it('preserves credit and readiness owner parity across all 180 seeded Students with bounded reads', function () {
    $this->seed(DatabaseSeeder::class);
    $users = User::query()->where('email', 'like', 'cohort.student.%@seed.ejust.test')->pluck('id');
    $ids = Student::query()->whereIn('user_id', $users)->orderBy('id')->pluck('id')->all();
    expect($ids)->toHaveCount(180);
    $credits = app(CreditHourSummaries::class);
    $progress = app(GraduationProgress::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    $summaries = $credits->forStudents($ids);
    $readiness = $progress->readinessForStudents($ids);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();
    expect($summaries)->toHaveCount(180)->and($readiness)->toHaveCount(180)
        ->and($queryCount)->toBeLessThan(200);
    foreach ($ids as $id) {
        expect($summaries[$id])->toEqual($credits->forStudent($id))
            ->and($readiness[$id])->toEqual($progress->readinessForStudent($id))
            ->and($readiness[$id]->approachingGraduation)->toBeFalse();
    }
});
