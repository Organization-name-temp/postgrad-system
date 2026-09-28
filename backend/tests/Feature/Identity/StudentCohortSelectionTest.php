<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\StudentDirectory;
use Support\Query\CollectionQuery;

uses(RefreshDatabase::class);

it('selects Student IDs using owner stored criteria and normalized bilingual search before any page limit', function () {
    $active = Student::factory()->create(['program_id' => 7, 'name_ar' => 'أَحمد علي', 'university_id' => '001-STUDENT']);
    $other = Student::factory()->create(['program_id' => 9, 'name_en' => 'Other Student']);
    Student::factory()->create(['program_id' => 7, 'lifecycle_status' => 'Terminated', 'name_ar' => 'أحمد سابق']);
    $query = fn (array $criteria): CollectionQuery => CollectionQuery::from(
        $criteria, ['program_id', 'lifecycle'], ['program_id', 'lifecycle'],
        ['student_id', 'name_en', 'name_ar', 'university_id'], stableKey: 'student_id',
        integerFilters: ['program_id'],
    );
    $directory = app(StudentDirectory::class);
    expect($directory->studentIdsMatching($query(['program_id' => '7', 'q' => 'احمد', 'per_page' => 1])))
        ->toBe([$active->id])
        ->and($directory->studentIdsMatching($query(['q' => '001-STUDENT'])))->toBe([$active->id])
        ->and($directory->studentIdsMatching($query(['program_id' => '7', 'lifecycle' => 'Terminated'])))->toHaveCount(1)
        ->and($directory->studentIdsMatching($query(['per_page' => 1])))->toBe([$active->id, $other->id]);
});
