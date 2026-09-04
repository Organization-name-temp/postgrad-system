<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;

uses(RefreshDatabase::class);

it('exposes Preparatory Requirement Roles through immutable Public Curriculum DTOs', function () {
    $preparatory = CurriculumEntry::factory()->create([
        'category' => 'Preparatory',
        'preparatory_requirement_role' => 'JapaneseCulture',
    ]);
    $core = CurriculumEntry::factory()->create([
        'program_id' => $preparatory->program_id,
        'category' => 'ProgramCore',
        'preparatory_requirement_role' => null,
    ]);

    $entries = app(CurriculumCatalog::class)->entriesForProgram($preparatory->program_id);

    expect($entries[$preparatory->course_id]->preparatoryRequirementRole)
        ->toBe(PreparatoryRequirementRole::JapaneseCulture)
        ->and($entries[$core->course_id]->preparatoryRequirementRole)->toBeNull();
});
