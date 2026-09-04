<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Modules\Catalog\Internal\Enums\VersionSubject;
use Modules\Catalog\Internal\Models\CatalogVersion;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Public\Contracts\StudentDirectory;

uses(RefreshDatabase::class);

it('backfills existing Students deterministically and audits the earliest-Version fallback', function () {
    $program = Program::factory()->create();
    $first = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 1,
        'snapshot' => ['curriculum' => []],
        'changed_at' => Carbon::parse('2026-02-01 09:00:00'),
    ]);
    $second = CatalogVersion::create([
        'subject_type' => VersionSubject::Program,
        'subject_id' => $program->id,
        'version_no' => 2,
        'snapshot' => ['curriculum' => []],
        'changed_at' => Carbon::parse('2026-03-01 09:00:00'),
    ]);

    $matched = Student::factory()->create([
        'program_id' => $program->id,
        'admitted_at' => Carbon::parse('2026-03-15 09:00:00'),
    ]);
    $fallback = Student::factory()->create([
        'program_id' => $program->id,
        'admitted_at' => Carbon::parse('2026-01-15 09:00:00'),
    ]);

    $addBinding = require base_path(
        'modules/Identity/Internal/Database/Migrations/2026_09_04_000001_add_student_curriculum_version_binding.php',
    );
    $backfill = require base_path(
        'database/migrations/2026_09_04_000002_backfill_student_curriculum_version_bindings.php',
    );
    $requireBinding = require base_path(
        'modules/Identity/Internal/Database/Migrations/2026_09_04_000003_require_student_curriculum_version_binding.php',
    );

    $requireBinding->down();
    $backfill->down();
    $addBinding->down();

    $addBinding->up();
    $backfill->up();
    $requireBinding->up();

    $directory = app(StudentDirectory::class);
    $matchedSummary = $directory->findByStudentId((int) $matched->id);
    $fallbackSummary = $directory->findByStudentId((int) $fallback->id);

    expect($matchedSummary?->curriculumVersionId)->toBe((int) $second->id)
        ->and($matchedSummary?->curriculumVersionBindingSource->value)->toBe('admission_time')
        ->and($fallbackSummary?->curriculumVersionId)->toBe((int) $first->id)
        ->and($fallbackSummary?->curriculumVersionBindingSource->value)->toBe('earliest_available_fallback');

    $this->assertDatabaseHas('audit_log', [
        'action' => 'student.curriculum_version_fallback_bound',
        'subject_type' => 'student',
        'subject_id' => (string) $fallback->id,
    ]);
    $this->assertDatabaseMissing('audit_log', [
        'action' => 'student.curriculum_version_fallback_bound',
        'subject_id' => (string) $matched->id,
    ]);
});
