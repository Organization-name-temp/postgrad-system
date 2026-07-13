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

it('lets a manager add a course to a program under a category', function () {
    $program = Program::factory()->create();
    $course = Course::factory()->create();
    actingAsRole(Role::DepartmentHead); // catalog.manage

    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
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
        $this->postJson("/api/v1/programs/{$program->id}/curriculum", ['course_id' => $prep->id, 'category' => 'Preparatory'])
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
