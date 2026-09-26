<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Public\BoundCurriculumEntrySummary;
use Modules\Catalog\Public\CurriculumCatalog;
use Modules\Catalog\Public\Enums\CourseCategory;
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

it('resolves semantic entries from the bound historical Curriculum Version', function () {
    $entry = CurriculumEntry::factory()->create([
        'category' => CourseCategory::Preparatory,
        'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage,
    ]);
    $version = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $entry->program_id,
        'version_no' => 1,
        'snapshot' => [
            'curriculum' => [[
                'course_id' => $entry->course_id,
                'course_code' => 'PRE-OLD',
                'course_total_ch' => 1,
                'category' => CourseCategory::Preparatory->value,
                'preparatory_requirement_role' => PreparatoryRequirementRole::EnglishLanguage->value,
                'ordinal' => 0,
            ]],
        ],
        'changed_at' => now(),
    ]);

    // A later live edit must not reinterpret the Student's bound snapshot.
    $entry->update(['preparatory_requirement_role' => PreparatoryRequirementRole::ArabicLanguage]);

    $entries = app(CurriculumCatalog::class)->entriesForVersion((int) $version->id);

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toBeInstanceOf(BoundCurriculumEntrySummary::class)
        ->and($entries[0]->programId)->toBe((int) $entry->program_id)
        ->and($entries[0]->courseId)->toBe((int) $entry->course_id)
        ->and($entries[0]->courseCode)->toBe('PRE-OLD')
        ->and($entries[0]->totalCh)->toBe(1)
        ->and($entries[0]->preparatoryRequirementRole)->toBe(PreparatoryRequirementRole::EnglishLanguage);
});
