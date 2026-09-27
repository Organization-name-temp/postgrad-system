<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\CurriculumCatalog;

uses(RefreshDatabase::class);

it('bulk reads immutable admission-bound requirements and entries independently of current curricula', function () {
    $program = Program::factory()->create();
    $bound = CatalogVersion::query()->create([
        'subject_type' => VersionSubject::Program, 'subject_id' => $program->id,
        'version_no' => 1, 'changed_at' => now(),
        'snapshot' => ['prep_required_ch' => 1, 'core_required_ch' => 6, 'elective_required_ch' => 3,
            'curriculum' => [['course_id' => 81, 'course_code' => 'BOUND-81', 'category' => 'ProgramCore', 'course_total_ch' => 3, 'ordinal' => 1]]],
    ]);
    CatalogVersion::query()->create([
        'subject_type' => VersionSubject::Program, 'subject_id' => $program->id,
        'version_no' => 2, 'changed_at' => now(),
        'snapshot' => ['prep_required_ch' => 0, 'core_required_ch' => 30, 'elective_required_ch' => 0, 'curriculum' => []],
    ]);
    $reader = app(CurriculumCatalog::class);
    $requirements = $reader->requirementsForVersions([$bound->id, $bound->id, 999999]);
    $entries = $reader->entriesForVersions([$bound->id, 999999]);
    expect(array_keys($requirements))->toBe([$bound->id])
        ->and($requirements[$bound->id]->programCoreCh)->toBe(6)
        ->and($entries[$bound->id][0]->courseCode)->toBe('BOUND-81')
        ->and($entries[$bound->id][0]->totalCh)->toBe(3)
        ->and($requirements[$bound->id])->toEqual($reader->requirementsForVersion($bound->id))
        ->and($entries[$bound->id])->toEqual($reader->entriesForVersion($bound->id))
        ->and($reader->requirementsForVersions([]))->toBe([])
        ->and($reader->entriesForVersions([]))->toBe([]);
});
