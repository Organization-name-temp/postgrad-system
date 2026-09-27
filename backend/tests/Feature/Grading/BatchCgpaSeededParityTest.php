<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Grading\Public\GradingRecords;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;

uses(RefreshDatabase::class);

it('batch CGPA seeded cohort parity gate agrees for every generated Student across bounded reads', function () {
    $this->seed(DatabaseSeeder::class);
    $userIds = User::query()->where('email', 'like', 'cohort.student.%@seed.ejust.test')->pluck('id');
    $ids = Student::query()->whereIn('user_id', $userIds)->orderBy('id')->pluck('id')->all();
    expect($ids)->toHaveCount(180);
    $records = app(GradingRecords::class);
    $values = $records->cgpaForStudents($ids);
    expect(array_keys($values))->toBe($ids);
    foreach ($ids as $id) {
        expect($values[$id])->toBe($records->cgpaFor($id));
    }
    expect($values)->toContain(null);
});
