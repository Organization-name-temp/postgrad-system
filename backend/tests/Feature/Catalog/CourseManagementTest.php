<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\CurriculumEntry;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('lets a viewer list courses, each carrying its full credit-hour breakdown', function () {
    Course::factory()->count(2)->create();
    actingAsRole(Role::Applicant); // holds catalog.view, not catalog.manage

    $this->getJson('/api/v1/courses')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [[
            'id', 'department_id', 'code', 'name_en', 'name_ar', 'level',
            'lecture_ch', 'tutorial_ch', 'lab_ch', 'total_ch', 'is_active',
        ]]]);
});

it('lets a viewer read a single course with both names, level and credit split', function () {
    $course = Course::factory()->create([
        'name_en' => 'Advanced Algorithms',
        'name_ar' => 'خوارزميات متقدمة',
        'level' => 500,
        'lecture_ch' => 3,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 3,
    ]);
    actingAsRole(Role::Student);

    $this->getJson("/api/v1/courses/{$course->id}")
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Advanced Algorithms')
        ->assertJsonPath('data.name_ar', 'خوارزميات متقدمة')
        ->assertJsonPath('data.level', 500)
        ->assertJsonPath('data.lecture_ch', 3)
        ->assertJsonPath('data.tutorial_ch', 0)
        ->assertJsonPath('data.lab_ch', 0)
        ->assertJsonPath('data.total_ch', 3);
});

it('shows which programs and categories a course is placed in', function () {
    $course = Course::factory()->create(['code' => 'PRE401']);
    $msc = Program::factory()->create(['code' => 'MSC-CE', 'name_en' => 'MSc in Computer Engineering', 'name_ar' => 'ماجستير']);
    $phd = Program::factory()->create(['code' => 'PHD-CE', 'name_en' => 'PhD in Computer Engineering', 'name_ar' => 'دكتوراه']);
    CurriculumEntry::factory()->create(['program_id' => $msc->id, 'course_id' => $course->id, 'category' => 'Preparatory']);
    CurriculumEntry::factory()->create(['program_id' => $phd->id, 'course_id' => $course->id, 'category' => 'Preparatory']);
    actingAsRole(Role::Applicant);

    $this->getJson("/api/v1/courses/{$course->id}")
        ->assertOk()
        ->assertJsonStructure(['data' => ['placements' => [['category', 'program' => ['id', 'code', 'name_en', 'name_ar']]]]])
        ->assertJsonCount(2, 'data.placements')
        // Ordered by program code (MSC before PHD would be alphabetical: MSC-CE, PHD-CE).
        ->assertJsonPath('data.placements.0.program.code', 'MSC-CE')
        ->assertJsonPath('data.placements.0.category', 'Preparatory')
        ->assertJsonPath('data.placements.0.program.name_en', 'MSc in Computer Engineering')
        ->assertJsonPath('data.placements.1.program.code', 'PHD-CE');
});

it('shows an empty placements list for a course in no program', function () {
    $course = Course::factory()->create();
    actingAsRole(Role::Applicant);

    $this->getJson("/api/v1/courses/{$course->id}")
        ->assertOk()
        ->assertJsonPath('data.placements', []);
});

it('lets a manager create a department-owned course, active by default', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead); // holds catalog.manage

    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id,
        'code' => 'DEP501',
        'name_en' => 'Advanced Algorithms',
        'name_ar' => 'خوارزميات متقدمة',
        'level' => 500,
        'lecture_ch' => 2,
        'tutorial_ch' => 1,
        'lab_ch' => 0,
        'total_ch' => 3,
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'DEP501')
        ->assertJsonPath('data.department_id', $department->id)
        ->assertJsonPath('data.level', 500)
        ->assertJsonPath('data.total_ch', 3)
        ->assertJsonPath('data.is_active', true);

    $this->assertDatabaseHas('courses', [
        'code' => 'DEP501', 'department_id' => $department->id,
        'lecture_ch' => 2, 'tutorial_ch' => 1, 'lab_ch' => 0, 'total_ch' => 3,
        'is_active' => true,
    ]);
});

it('lets a manager create a university-wide course with no department, and round-trips the null', function () {
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/courses', [
        'code' => 'PRE401',
        'name_en' => 'English for Academic Purposes',
        'name_ar' => 'الإنجليزية للأغراض الأكاديمية',
        'level' => 400,
        'lecture_ch' => 3,
        'tutorial_ch' => 0,
        'lab_ch' => 0,
        'total_ch' => 3,
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'PRE401')
        ->assertJsonPath('data.department_id', null);

    $this->assertDatabaseHas('courses', ['code' => 'PRE401', 'department_id' => null]);

    // The null round-trips on read too — the SPA renders it as "university-wide".
    $course = Course::query()->where('code', 'PRE401')->firstOrFail();
    actingAsRole(Role::Applicant);
    $this->getJson("/api/v1/courses/{$course->id}")
        ->assertOk()
        ->assertJsonPath('data.department_id', null);
});

it('rejects a course whose lecture + tutorial + lab does not equal the total', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id,
        'code' => 'DEP502',
        'name_en' => 'Broken Course',
        'name_ar' => 'مقرر خاطئ',
        'level' => 500,
        'lecture_ch' => 2,
        'tutorial_ch' => 1,
        'lab_ch' => 0,
        'total_ch' => 4, // 2 + 1 + 0 = 3, not 4
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('total_ch');

    $this->assertDatabaseMissing('courses', ['code' => 'DEP502']);
});

it('requires both language names', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id, 'code' => 'DEP501', 'level' => 500,
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name_en', 'name_ar']);
});

it('requires the level and constrains it to the 400/500/600/700 enum', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    // Missing
    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id, 'code' => 'DEP501',
        'name_en' => 'A', 'name_ar' => 'أ',
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('level');

    // Not one of the enum values
    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id, 'code' => 'DEP501',
        'name_en' => 'A', 'name_ar' => 'أ', 'level' => 450,
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('level');
});

it('rejects a duplicate course code', function () {
    Course::factory()->create(['code' => 'DEP501']);
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id, 'code' => 'DEP501',
        'name_en' => 'Duplicate', 'name_ar' => 'مكرر', 'level' => 500,
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('rejects a non-existent department (when one is supplied)', function () {
    actingAsRole(Role::DepartmentHead);

    $this->postJson('/api/v1/courses', [
        'department_id' => 999999, 'code' => 'DEP501',
        'name_en' => 'A', 'name_ar' => 'أ', 'level' => 500,
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('department_id');
});

it('rejects a negative or missing credit-hour component', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    // Negative lecture hours
    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id, 'code' => 'DEP501',
        'name_en' => 'A', 'name_ar' => 'أ', 'level' => 500,
        'lecture_ch' => -1, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('lecture_ch');

    // Missing total
    $this->postJson('/api/v1/courses', [
        'department_id' => $department->id, 'code' => 'DEP502',
        'name_en' => 'A', 'name_ar' => 'أ', 'level' => 500,
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('total_ch');
});

it('lets a manager edit a course name and credit breakdown', function () {
    $course = Course::factory()->create([
        'code' => 'DEP501', 'name_en' => 'Old', 'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ]);
    actingAsRole(Role::Dean);

    $this->patchJson("/api/v1/courses/{$course->id}", [
        'department_id' => $course->department_id,
        'code' => 'DEP501',
        'name_en' => 'Advanced Algorithms',
        'name_ar' => 'خوارزميات متقدمة',
        'level' => 500,
        'lecture_ch' => 2,
        'tutorial_ch' => 1,
        'lab_ch' => 1,
        'total_ch' => 4,
    ])
        ->assertOk()
        ->assertJsonPath('data.name_en', 'Advanced Algorithms')
        ->assertJsonPath('data.total_ch', 4);

    $this->assertDatabaseHas('courses', ['id' => $course->id, 'name_en' => 'Advanced Algorithms', 'total_ch' => 4]);
});

it('lets a manager convert a department-owned course to university-wide by editing', function () {
    $course = Course::factory()->create();
    actingAsRole(Role::Dean);

    $this->patchJson("/api/v1/courses/{$course->id}", [
        // department_id omitted → university-wide. The edit is a full replacement,
        // so an absent owner clears it (this is how the SPA form sends it).
        'code' => $course->code,
        'name_en' => $course->name_en,
        'name_ar' => $course->name_ar,
        'level' => $course->level->value,
        'lecture_ch' => $course->lecture_ch,
        'tutorial_ch' => $course->tutorial_ch,
        'lab_ch' => $course->lab_ch,
        'total_ch' => $course->total_ch,
    ])
        ->assertOk()
        ->assertJsonPath('data.department_id', null);

    $this->assertDatabaseHas('courses', ['id' => $course->id, 'department_id' => null]);
});

it('lets a manager deactivate then reactivate a course, leaving it readable in between', function () {
    $course = Course::factory()->create(['is_active' => true]);
    actingAsRole(Role::DepartmentHead);

    $this->postJson("/api/v1/courses/{$course->id}/deactivate")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);
    $this->assertDatabaseHas('courses', ['id' => $course->id, 'is_active' => false]);

    // Deactivating does not delete or hide it — a viewer can still read it.
    actingAsRole(Role::Applicant);
    $this->getJson("/api/v1/courses/{$course->id}")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    actingAsRole(Role::DepartmentHead);
    $this->postJson("/api/v1/courses/{$course->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.is_active', true);
});

it('forbids mutations for a viewer without catalog.manage', function () {
    $course = Course::factory()->create();
    $payload = [
        'code' => 'X', 'name_en' => 'x', 'name_ar' => 'س', 'level' => 500,
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ];
    actingAsRole(Role::Applicant); // catalog.view only

    $this->postJson('/api/v1/courses', $payload)->assertForbidden();
    $this->patchJson("/api/v1/courses/{$course->id}", $payload)->assertForbidden();
    $this->postJson("/api/v1/courses/{$course->id}/activate")->assertForbidden();
    $this->postJson("/api/v1/courses/{$course->id}/deactivate")->assertForbidden();
});

it('does not allow hard-deleting a course', function () {
    $course = Course::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $this->deleteJson("/api/v1/courses/{$course->id}")->assertStatus(405);
});
