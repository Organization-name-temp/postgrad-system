<?php

use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\PreparatoryRequirementRole;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('seeds all three faculties with bilingual names', function () {
    $this->seed(CatalogSeeder::class);

    foreach (['FoE', 'BAS', 'CSIT'] as $code) {
        $this->assertDatabaseHas('faculties', ['code' => $code]);
    }

    $foe = Faculty::query()->where('code', 'FoE')->firstOrFail();
    expect($foe->name_en)->not->toBe('');
    expect($foe->name_ar)->not->toBe('');
    // The Arabic name is genuinely Arabic, not an English placeholder.
    expect($foe->name_ar)->toMatch('/\p{Arabic}/u');
});

it('fully builds FoE → Computer Engineering → MSc & PhD from the FR-PCM-002 example', function () {
    $this->seed(CatalogSeeder::class);
    actingAsRole(Role::Applicant);

    $faculties = collect($this->getJson('/api/v1/catalog/tree')->assertOk()->json('data.faculties'));

    $foe = $faculties->firstWhere('code', 'FoE');
    expect($foe)->not->toBeNull();

    $ce = collect($foe['departments'])->firstWhere('code', 'CE');
    expect($ce)->not->toBeNull();

    $programs = collect($ce['programs']);
    $msc = $programs->firstWhere('code', 'MSC-CE');
    $phd = $programs->firstWhere('code', 'PHD-CE');
    expect($msc)->not->toBeNull();
    expect($phd)->not->toBeNull();
    expect($msc['degree_level'])->toBe('MSc');
    expect($phd['degree_level'])->toBe('PhD');

    // The MSc curriculum matches the bylaw shape: Preparatory 6 (PRE401–404 + DEP501/502),
    // Program Core 2, Research Elective 3.
    $categories = collect($msc['categories'])->keyBy('category');
    expect($categories['Preparatory']['courses'])->toHaveCount(6);
    expect($categories['ProgramCore']['courses'])->toHaveCount(2);
    expect($categories['ResearchElective']['courses'])->toHaveCount(3);

    $prepCourses = collect($categories['Preparatory']['courses']);
    expect($prepCourses->pluck('code'))->toContain('PRE401', 'PRE404', 'DEP501', 'DEP502');

    // Genuine bilingual content, not an English placeholder in the Arabic column.
    $pre401 = $prepCourses->firstWhere('code', 'PRE401');
    expect($pre401['name_ar'])->toMatch('/\p{Arabic}/u');
});

it('links the shared university-wide preparatory courses into every program', function () {
    $this->seed(CatalogSeeder::class);

    $pre401 = Course::query()->where('code', 'PRE401')->firstOrFail();
    // University-wide: owned by no Department (ADR-0010).
    expect($pre401->department_id)->toBeNull();

    // It is placed in more than one Program — reuse, not duplication.
    $programIds = $pre401->curriculumEntries()->pluck('program_id')->unique();
    expect($programIds->count())->toBeGreaterThan(1);
});

it('assigns every seeded Program exactly one of each Preparatory Requirement Role', function () {
    $this->seed(CatalogSeeder::class);
    $expected = collect(PreparatoryRequirementRole::cases())->map->value->sort()->values()->all();

    Program::query()->with(['curriculumEntries.course'])->get()->each(function (Program $program) use ($expected): void {
        $preparatory = $program->curriculumEntries
            ->where('category.value', 'Preparatory');

        expect($preparatory)->toHaveCount(6)
            ->and($preparatory->pluck('preparatory_requirement_role.value')->sort()->values()->all())
            ->toBe($expected);

        /** @var CurriculumEntry $keySkills */
        $keySkills = $preparatory->firstWhere(
            'preparatory_requirement_role.value',
            PreparatoryRequirementRole::KeySkills->value,
        );
        expect($keySkills->course->department_id)->toBe($program->department_id);
    });
});

it('keeps every seeded course credit-hour balanced (L + T + Lab = Total)', function () {
    $this->seed(CatalogSeeder::class);

    Course::all()->each(function (Course $course): void {
        expect($course->lecture_ch + $course->tutorial_ch + $course->lab_ch)->toBe($course->total_ch);
    });
});

it('seeds idempotently', function () {
    $this->seed(CatalogSeeder::class);
    $faculties = Faculty::count();
    $courses = Course::count();

    // Running again must not duplicate anything.
    $this->seed(CatalogSeeder::class);

    expect(Faculty::count())->toBe($faculties);
    expect(Course::count())->toBe($courses);
});
