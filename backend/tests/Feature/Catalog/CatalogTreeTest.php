<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

/**
 * Build the FR-PCM-002 slice: one Faculty → Department → Program with a
 * Preparatory course and two Program Core courses. Returns the created models so a
 * test can assert against their ids/codes.
 *
 * @return array{faculty: Faculty, department: Department, program: Program, prep: Course, core: Course}
 */
function seedTreeSlice(): array
{
    $faculty = Faculty::factory()->create([
        'code' => 'FoE', 'name_en' => 'Faculty of Engineering', 'name_ar' => 'كلية الهندسة',
    ]);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id, 'code' => 'CE',
        'name_en' => 'Computer Engineering', 'name_ar' => 'هندسة الحاسوب',
    ]);
    $program = Program::factory()->create([
        'department_id' => $department->id, 'code' => 'MSC-CE', 'degree_level' => 'MSc',
        'name_en' => 'MSc in Computer Engineering', 'name_ar' => 'ماجستير في هندسة الحاسوب',
    ]);

    $prep = Course::factory()->universityWide()->create([
        'code' => 'PRE401', 'name_en' => 'English for Academic Purposes',
        'name_ar' => 'الإنجليزية للأغراض الأكاديمية', 'level' => 400,
    ]);
    $core = Course::factory()->create([
        'department_id' => $department->id, 'code' => 'DEP501',
        'name_en' => 'Advanced Algorithms', 'name_ar' => 'خوارزميات متقدمة', 'level' => 500,
    ]);

    CurriculumEntry::factory()->create([
        'program_id' => $program->id, 'course_id' => $prep->id, 'category' => 'Preparatory', 'ordinal' => 0,
    ]);
    CurriculumEntry::factory()->create([
        'program_id' => $program->id, 'course_id' => $core->id, 'category' => 'ProgramCore', 'ordinal' => 0,
    ]);

    return compact('faculty', 'department', 'program', 'prep', 'core');
}

it('returns the whole nested tree in one payload, bilingual on every node', function () {
    seedTreeSlice();
    actingAsRole(Role::Applicant); // catalog.view, not catalog.manage

    $this->getJson('/api/v1/catalog/tree')
        ->assertOk()
        ->assertJsonStructure(['data' => ['faculties' => [[
            'id', 'code', 'name_en', 'name_ar', 'is_active',
            'departments' => [[
                'id', 'code', 'name_en', 'name_ar', 'is_active',
                'programs' => [[
                    'id', 'code', 'name_en', 'name_ar', 'degree_level', 'is_active',
                    'categories' => [[
                        'category', 'required_ch',
                        'courses' => [['id', 'code', 'name_en', 'name_ar', 'level', 'is_active']],
                    ]],
                ]],
            ]],
        ]]]])
        ->assertJsonPath('data.faculties.0.code', 'FoE')
        ->assertJsonPath('data.faculties.0.name_en', 'Faculty of Engineering')
        ->assertJsonPath('data.faculties.0.name_ar', 'كلية الهندسة')
        ->assertJsonPath('data.faculties.0.departments.0.code', 'CE')
        ->assertJsonPath('data.faculties.0.departments.0.name_ar', 'هندسة الحاسوب')
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.code', 'MSC-CE')
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.degree_level', 'MSc')
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.name_ar', 'ماجستير في هندسة الحاسوب');
});

it('lists all three categories in bylaw order with their required credit hours', function () {
    seedTreeSlice();
    actingAsRole(Role::Applicant);

    $categories = $this->getJson('/api/v1/catalog/tree')
        ->assertOk()
        ->json('data.faculties.0.departments.0.programs.0.categories');

    expect($categories)->toHaveCount(3);
    expect(array_column($categories, 'category'))
        ->toBe(['Preparatory', 'ProgramCore', 'ResearchElective']);
    expect(array_column($categories, 'required_ch'))->toBe([6, 6, 9]);
});

it('places each course under its program category, with both names and status', function () {
    seedTreeSlice();
    actingAsRole(Role::Student);

    $this->getJson('/api/v1/catalog/tree')
        ->assertOk()
        // Preparatory holds the shared PRE401 (university-wide course)…
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.categories.0.courses.0.code', 'PRE401')
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.categories.0.courses.0.name_ar', 'الإنجليزية للأغراض الأكاديمية')
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.categories.0.courses.0.is_active', true)
        // …Program Core holds DEP501; Research Elective is empty for this program.
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.categories.1.courses.0.code', 'DEP501')
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.categories.2.courses', []);
});

it('carries a course active status so retired courses read accessibly', function () {
    $slice = seedTreeSlice();
    $retired = Course::factory()->inactive()->create([
        'department_id' => $slice['department']->id, 'code' => 'OLD500',
    ]);
    CurriculumEntry::factory()->create([
        'program_id' => $slice['program']->id, 'course_id' => $retired->id,
        'category' => 'ResearchElective', 'ordinal' => 0,
    ]);
    actingAsRole(Role::Applicant);

    $this->getJson('/api/v1/catalog/tree')
        ->assertOk()
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.categories.2.courses.0.code', 'OLD500')
        ->assertJsonPath('data.faculties.0.departments.0.programs.0.categories.2.courses.0.is_active', false);
});

it('groups a shared course under every program and category it is placed in', function () {
    $course = Course::factory()->universityWide()->create(['code' => 'PRE401']);
    $msc = Program::factory()->create(['code' => 'MSC-CE']);
    $phd = Program::factory()->create(['code' => 'PHD-CE']);
    CurriculumEntry::factory()->create(['program_id' => $msc->id, 'course_id' => $course->id, 'category' => 'Preparatory', 'ordinal' => 0]);
    CurriculumEntry::factory()->create(['program_id' => $phd->id, 'course_id' => $course->id, 'category' => 'Preparatory', 'ordinal' => 0]);
    actingAsRole(Role::Applicant);

    // The same course code surfaces under two different programs' Preparatory
    // category — reuse via curriculum entries, not duplication (ADR-0010).
    $codes = collect($this->getJson('/api/v1/catalog/tree')->assertOk()->json('data.faculties'))
        ->flatMap(fn (array $faculty) => $faculty['departments'])
        ->flatMap(fn (array $department) => $department['programs'])
        ->flatMap(fn (array $program) => $program['categories'])
        ->flatMap(fn (array $category) => array_column($category['courses'], 'code'));

    expect($codes->filter(fn (string $code) => $code === 'PRE401'))->toHaveCount(2);
});

it('orders faculties, and courses within a category, deterministically', function () {
    // Insert out of alphabetical order to prove the endpoint sorts, not the DB.
    Faculty::factory()->create(['code' => 'FoE']);
    Faculty::factory()->create(['code' => 'BAS']);
    actingAsRole(Role::Applicant);

    $codes = collect($this->getJson('/api/v1/catalog/tree')->assertOk()->json('data.faculties'))
        ->pluck('code');

    // Alphabetical by code — stable regardless of insertion order.
    expect($codes->all())->toBe(['BAS', 'FoE']);
});

it('requires an authenticated session', function () {
    $this->getJson('/api/v1/catalog/tree')->assertUnauthorized();
});

it('requires the catalog.view permission', function () {
    // A user with no roles holds no permissions — the gate must bite.
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/catalog/tree')->assertForbidden();
});
