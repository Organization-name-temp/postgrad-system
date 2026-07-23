<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Public\Enums\Role;

uses(RefreshDatabase::class);

it('records version 1 when a program is created, in the catalog table and not the audit log', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $response = $this->postJson('/api/v1/programs', [
        'department_id' => $department->id,
        'code' => 'MSC-CE',
        'name_en' => 'MSc in Computer Engineering',
        'name_ar' => 'ماجستير في هندسة الحاسبات',
        'degree_level' => 'MSc',
    ])->assertCreated();

    $programId = $response->json('data.id');

    $this->assertDatabaseHas('catalog_versions', [
        'subject_type' => 'program',
        'subject_id' => $programId,
        'version_no' => 1,
        'change_note' => 'created',
    ]);

    // Version history is Catalog-owned domain content — it never flows through the
    // Identity AuditLogger, so the security audit log stays empty (ADR-0011).
    $this->assertDatabaseCount('catalog_versions', 1);
    $this->assertDatabaseCount('audit_log', 0);
});

it('increments the program version_no on each edit/activate/deactivate', function () {
    $program = Program::factory()->create(); // created directly, no version yet
    actingAsRole(Role::Dean);

    $payload = [
        'department_id' => $program->department_id,
        'code' => $program->code,
        'name_en' => 'Renamed',
        'name_ar' => 'مُعاد التسمية',
        'degree_level' => $program->degree_level->value,
    ];
    $this->patchJson("/api/v1/programs/{$program->id}", $payload)->assertOk();
    $this->postJson("/api/v1/programs/{$program->id}/deactivate")->assertOk();
    $this->postJson("/api/v1/programs/{$program->id}/activate")->assertOk();

    $versions = $this->getJson("/api/v1/programs/{$program->id}/versions")->assertOk();
    $versions->assertJsonCount(3, 'data');
    $versions->assertJsonPath('data.0.version_no', 1);
    $versions->assertJsonPath('data.0.change_note', 'updated');
    $versions->assertJsonPath('data.1.change_note', 'deactivated');
    $versions->assertJsonPath('data.2.change_note', 'activated');
});

it('captures the full program aggregate — including its curriculum — in the snapshot', function () {
    $program = Program::factory()->create(['prep_required_ch' => 6, 'core_required_ch' => 6, 'elective_required_ch' => 9]);
    $course = Course::factory()->create(['code' => 'DEP501']);
    actingAsRole(Role::DepartmentHead);

    // Adding a curriculum entry is a versioned Program change (ADR-0011).
    $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $course->id,
        'category' => 'ProgramCore',
    ])->assertCreated();

    $latest = $this->getJson("/api/v1/programs/{$program->id}/versions")
        ->assertOk()
        ->assertJsonPath('data.0.change_note', 'curriculum_updated')
        ->json('data.0.snapshot');

    // The snapshot carries the scalar aggregate...
    expect($latest['code'])->toBe($program->code);
    expect($latest['prep_required_ch'])->toBe(6);
    expect($latest['total_required_ch'])->toBe(21);
    // ...and the curriculum it now contains.
    expect($latest['curriculum'])->toHaveCount(1);
    expect($latest['curriculum'][0]['course_code'])->toBe('DEP501');
    expect($latest['curriculum'][0]['category'])->toBe('ProgramCore');
});

it('records a new program version when a curriculum entry is removed', function () {
    $program = Program::factory()->create();
    $course = Course::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $entry = $this->postJson("/api/v1/programs/{$program->id}/curriculum", [
        'course_id' => $course->id, 'category' => 'Preparatory',
    ])->assertCreated()->json('data.id');

    $this->deleteJson("/api/v1/programs/{$program->id}/curriculum/{$entry}")->assertNoContent();

    $versions = $this->getJson("/api/v1/programs/{$program->id}/versions")->assertOk();
    $versions->assertJsonCount(2, 'data'); // add, then remove
    $versions->assertJsonPath('data.1.change_note', 'curriculum_updated');
    // The removal snapshot reflects the now-empty curriculum.
    expect($versions->json('data.1.snapshot.curriculum'))->toHaveCount(0);
});

it('records and increments course versions on each mutation, capturing the course fields', function () {
    actingAsRole(Role::DepartmentHead);

    $courseId = $this->postJson('/api/v1/courses', [
        'code' => 'DEP501', 'name_en' => 'Advanced Algorithms', 'name_ar' => 'خوارزميات',
        'level' => 500, 'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])->assertCreated()->json('data.id');

    $this->postJson("/api/v1/courses/{$courseId}/deactivate")->assertOk();

    $versions = $this->getJson("/api/v1/courses/{$courseId}/versions")->assertOk();
    $versions->assertJsonCount(2, 'data');
    $versions->assertJsonPath('data.0.version_no', 1);
    $versions->assertJsonPath('data.0.change_note', 'created');
    $versions->assertJsonPath('data.1.change_note', 'deactivated');
    expect($versions->json('data.0.snapshot.code'))->toBe('DEP501');
    expect($versions->json('data.0.snapshot.total_ch'))->toBe(3);
});

it('captures setting and later changing the research type across course version snapshots (ticket 02)', function () {
    actingAsRole(Role::DepartmentHead);

    $courseId = $this->postJson('/api/v1/courses', [
        'code' => 'DEP701', 'name_en' => 'Problem-Based Research', 'name_ar' => 'بحث',
        'level' => 700, 'research_type' => 'PBL',
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])->assertCreated()->json('data.id');

    // Changing the research type is an ordinary Course edit — the full-aggregate
    // snapshot records the new value in a fresh version (no separate path).
    $this->patchJson("/api/v1/courses/{$courseId}", [
        'code' => 'DEP701', 'name_en' => 'Problem-Based Research', 'name_ar' => 'بحث',
        'level' => 700, 'research_type' => 'Seminar',
        'lecture_ch' => 3, 'tutorial_ch' => 0, 'lab_ch' => 0, 'total_ch' => 3,
    ])->assertOk();

    $versions = $this->getJson("/api/v1/courses/{$courseId}/versions")->assertOk();
    $versions->assertJsonCount(2, 'data');
    // v1 (created) captured PBL; v2 (updated) captures the change to Seminar.
    expect($versions->json('data.0.change_note'))->toBe('created');
    expect($versions->json('data.0.snapshot.research_type'))->toBe('PBL');
    expect($versions->json('data.1.change_note'))->toBe('updated');
    expect($versions->json('data.1.snapshot.research_type'))->toBe('Seminar');
});

it('lets any catalog.view holder read version history (a read like other catalog reads)', function () {
    $program = Program::factory()->create();
    $course = Course::factory()->create();
    actingAsRole(Role::Applicant); // catalog.view only — no catalog.manage

    // History is a read (spec §API "all reads require catalog.view"; story 35), so a
    // viewer can read it even though only a manager can produce new versions.
    $this->getJson("/api/v1/programs/{$program->id}/versions")->assertOk();
    $this->getJson("/api/v1/courses/{$course->id}/versions")->assertOk();
});

it('does not allow editing or deleting version history through the API', function () {
    $department = Department::factory()->create();
    actingAsRole(Role::DepartmentHead);

    $programId = $this->postJson('/api/v1/programs', [
        'department_id' => $department->id, 'code' => 'MSC-CE',
        'name_en' => 'MSc', 'name_ar' => 'ماجستير', 'degree_level' => 'MSc',
    ])->assertCreated()->json('data.id');

    // The history is read-only: no write verbs are routed on the versions resource.
    $this->postJson("/api/v1/programs/{$programId}/versions", [])->assertStatus(405);
    $this->patchJson("/api/v1/programs/{$programId}/versions", [])->assertStatus(405);
    $this->deleteJson("/api/v1/programs/{$programId}/versions")->assertStatus(405);
});
