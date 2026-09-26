<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('lets a viewer read a program curriculum with each entry carrying its category and course', function () {
    $program = Program::factory()->create();
    $core = Course::factory()->create(['code' => 'DEP501', 'name_en' => 'Advanced Algorithms']);
    $prep = Course::factory()->create(['code' => 'PRE401']);
    CurriculumEntry::factory()->create(['program_id' => $program->id, 'course_id' => $core->id, 'category' => 'ProgramCore']);
    CurriculumEntry::factory()->create(['program_id' => $program->id, 'course_id' => $prep->id, 'category' => 'Preparatory']);
    actingAsRole(Role::Applicant); // catalog.view, not catalog.manage

    $this->getJson("/api/v1/programs/{$program->id}/curriculum")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [[
            'id', 'program_id', 'category', 'ordinal',
            'course' => ['id', 'code', 'name_en', 'name_ar', 'level', 'is_active'],
        ]]])
        ->assertJsonPath('data.0.course.code', 'DEP501'); // ordinal tie → course-code tiebreak
});

it('reports which semantic roles are missing before a Program Curriculum is complete', function () {
    $program = Program::factory()->create();
    $english = Course::factory()->universityWide()->create(['code' => 'PRE401']);
    CurriculumEntry::factory()->create([
        'program_id' => $program->id,
        'course_id' => $english->id,
        'category' => 'Preparatory',
        'preparatory_requirement_role' => 'EnglishLanguage',
    ]);
    actingAsRole(Role::Applicant);

    $this->getJson("/api/v1/programs/{$program->id}/curriculum")
        ->assertOk()
        ->assertJsonPath('meta.preparatory_requirements_complete', false)
        ->assertJsonPath('meta.missing_preparatory_requirement_roles', [
            'ArabicLanguage',
            'JapaneseLanguage',
            'JapaneseCulture',
            'KeySkills',
            'DepartmentalRequirement',
        ]);
});

it('lets a manager add a course to a program under a category', function () {
    $program = Program::factory()->create();
    $course = Course::factory()->create();
    actingAsRole(Role::DepartmentHead); // catalog.manage

    $this->withHeader('Accept-Language', 'en')->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $course->id,
        'category' => 'ProgramCore',
    ])
        ->assertCreated()
        ->assertJsonPath('data.category', 'ProgramCore')
        ->assertJsonPath('data.course.id', $course->id);

    $this->assertDatabaseHas('curriculum_entries', [
        'program_id' => $program->id, 'course_id' => $course->id, 'category' => 'ProgramCore',
    ]);
});

it('requires and exposes a semantic role when adding a Preparatory Curriculum Entry', function () {
    $program = Program::factory()->create();
    $course = Course::factory()->universityWide()->create(['code' => 'PRE401']);
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $course->id,
        'category' => 'Preparatory',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('preparatory_requirement_role');

    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $course->id,
        'category' => 'Preparatory',
        'preparatory_requirement_role' => 'EnglishLanguage',
    ])
        ->assertCreated()
        ->assertJsonPath('data.preparatory_requirement_role', 'EnglishLanguage');

    $this->assertDatabaseHas('curriculum_entries', [
        'program_id' => $program->id,
        'course_id' => $course->id,
        'category' => 'Preparatory',
        'preparatory_requirement_role' => 'EnglishLanguage',
    ]);
});

it('returns bilingual errors for missing and non-Preparatory role assignments', function () {
    $program = Program::factory()->create();
    $preparatory = Course::factory()->universityWide()->create();
    $core = Course::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $preparatory->id,
        'category' => 'Preparatory',
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.preparatory_requirement_role.0',
            'Every Preparatory Curriculum Entry requires a Preparatory Requirement Role.',
        );

    $this->withHeader('Accept-Language', 'ar')->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $preparatory->id,
        'category' => 'Preparatory',
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.preparatory_requirement_role.0',
            'يتطلب كل مدخل تمهيدي في الخطة الدراسية دور متطلب تمهيدي.',
        );

    $this->withHeader('Accept-Language', 'en')->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $core->id,
        'category' => 'ProgramCore',
        'preparatory_requirement_role' => 'EnglishLanguage',
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.preparatory_requirement_role.0',
            'Only Preparatory Curriculum Entries may have a Preparatory Requirement Role.',
        );
});

it('rejects duplicate Preparatory Requirement Roles with bilingual validation errors', function () {
    $program = Program::factory()->create();
    $english = Course::factory()->universityWide()->create(['code' => 'PRE401']);
    $duplicate = Course::factory()->universityWide()->create(['code' => 'PRE499']);
    actingAsRole(Role::DepartmentHead);

    $payload = [
        'category' => 'Preparatory',
        'preparatory_requirement_role' => 'EnglishLanguage',
    ];

    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        ...$payload,
        'course_id' => $english->id,
    ])->assertCreated();

    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        ...$payload,
        'course_id' => $duplicate->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.preparatory_requirement_role.0',
            'This Preparatory Requirement Role is already assigned in this Program.',
        );

    $this->withHeader('Accept-Language', 'ar')->postJson("/api/v1/programs/{$program->id}/curriculum", [
        ...$payload,
        'course_id' => $duplicate->id,
    ])
        ->assertUnprocessable()
        ->assertJsonPath(
            'errors.preparatory_requirement_role.0',
            'دور المتطلب التمهيدي هذا مُعيّن بالفعل في هذا البرنامج.',
        );
});

it('requires the Key Skills role to target a Course owned by the Programs Department', function () {
    $program = Program::factory()->create();
    $universityWide = Course::factory()->universityWide()->create(['code' => 'PRE405']);
    $otherDepartment = Course::factory()->create(['code' => 'OTHER501']);
    $departmental = Course::factory()->create([
        'code' => 'DEP501',
        'department_id' => $program->department_id,
    ]);
    actingAsRole(Role::DepartmentHead);

    foreach ([$universityWide, $otherDepartment] as $course) {
        $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
            'course_id' => $course->id,
            'category' => 'Preparatory',
            'preparatory_requirement_role' => 'KeySkills',
        ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.preparatory_requirement_role.0',
                'Key Skills must be assigned to a Course owned by this Program’s Department.',
            );
    }

    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $departmental->id,
        'category' => 'Preparatory',
        'preparatory_requirement_role' => 'KeySkills',
    ])->assertCreated();
});

it('lets a manager correct the role of an existing Preparatory Curriculum Entry', function () {
    $entry = CurriculumEntry::factory()->create([
        'category' => 'Preparatory',
        'preparatory_requirement_role' => 'ArabicLanguage',
    ]);
    actingAsRole(Role::DepartmentHead);

    $this->patchJson("/api/v1/programs/{$entry->program_id}/curriculum/{$entry->id}", [
        'preparatory_requirement_role' => 'JapaneseLanguage',
    ])
        ->assertOk()
        ->assertJsonPath('data.preparatory_requirement_role', 'JapaneseLanguage');

    $this->assertDatabaseHas('curriculum_entries', [
        'id' => $entry->id,
        'preparatory_requirement_role' => 'JapaneseLanguage',
    ]);
});

it('lets a manager remove a curriculum entry', function () {
    $entry = CurriculumEntry::factory()->create();
    actingAsRole(Role::Dean);

    $this->deleteJson("/api/v1/programs/{$entry->program_id}/curriculum/{$entry->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('curriculum_entries', ['id' => $entry->id]);
});

it('places the same course in two programs under different categories', function () {
    $course = Course::factory()->create(['code' => 'CSE511']);
    $msc = Program::factory()->create(['code' => 'MSC-CE']);
    $phd = Program::factory()->create(['code' => 'PHD-CE']);
    actingAsRole(Role::DepartmentHead);

    // Core in the MSc...
    $this->postJson("/api/v1/programs/{$msc->id}/curriculum", ['course_id' => $course->id, 'category' => 'ProgramCore'])
        ->assertCreated();
    // ...and an (external) Research Elective in the PhD.
    $this->postJson("/api/v1/programs/{$phd->id}/curriculum", ['course_id' => $course->id, 'category' => 'ResearchElective'])
        ->assertCreated();

    actingAsRole(Role::Student);
    $this->getJson("/api/v1/programs/{$msc->id}/curriculum")
        ->assertOk()
        ->assertJsonPath('data.0.category', 'ProgramCore')
        ->assertJsonPath('data.0.course.code', 'CSE511');
    $this->getJson("/api/v1/programs/{$phd->id}/curriculum")
        ->assertOk()
        ->assertJsonPath('data.0.category', 'ResearchElective')
        ->assertJsonPath('data.0.course.code', 'CSE511');
});

it('places a shared preparatory course across multiple programs', function () {
    $prep = Course::factory()->universityWide()->create(['code' => 'PRE401']);
    $programs = Program::factory()->count(3)->create();
    actingAsRole(Role::DepartmentHead);

    foreach ($programs as $program) {
        $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
            'course_id' => $prep->id,
            'category' => 'Preparatory',
            'preparatory_requirement_role' => 'EnglishLanguage',
        ])
            ->assertCreated();
    }

    $this->assertDatabaseCount('curriculum_entries', 3);
});

it('rejects a duplicate entry for the same program, course and category', function () {
    $entry = CurriculumEntry::factory()->create(['category' => 'ProgramCore']);
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/programs/{$entry->program_id}/curriculum", [
        'course_id' => $entry->course_id,
        'category' => 'ProgramCore',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('course_id');
});

it('allows the same course in one program under two different categories', function () {
    $entry = CurriculumEntry::factory()->create(['category' => 'ProgramCore']);
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/programs/{$entry->program_id}/curriculum", [
        'course_id' => $entry->course_id,
        'category' => 'ResearchElective',
    ])
        ->assertCreated();
});

it('requires a valid course and category', function () {
    $program = Program::factory()->create();
    actingAsRole(Role::DepartmentHead);

    // Missing both
    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['course_id', 'category']);

    // Non-existent course
    $this->postJson("/api/v1/programs/{$program->id}/curriculum", ['course_id' => 999999, 'category' => 'ProgramCore'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('course_id');

    // Category outside the enum
    $course = Course::factory()->create();
    $this->postJson("/api/v1/programs/{$program->id}/curriculum", ['course_id' => $course->id, 'category' => 'Nonsense'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('category');
});

it('forbids composing the curriculum without catalog.manage', function () {
    $entry = CurriculumEntry::factory()->create();
    $course = Course::factory()->create();
    actingAsRole(Role::Applicant); // catalog.view only

    $this->postJson("/api/v1/programs/{$entry->program_id}/curriculum", ['course_id' => $course->id, 'category' => 'ProgramCore'])
        ->assertForbidden();
    $this->deleteJson("/api/v1/programs/{$entry->program_id}/curriculum/{$entry->id}")
        ->assertForbidden();
});

it('does not remove an entry that belongs to a different program', function () {
    $entry = CurriculumEntry::factory()->create();
    $otherProgram = Program::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->deleteJson("/api/v1/programs/{$otherProgram->id}/curriculum/{$entry->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('curriculum_entries', ['id' => $entry->id]);
});
