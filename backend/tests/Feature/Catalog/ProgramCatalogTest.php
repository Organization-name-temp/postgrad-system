<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Catalog\Public\Enums\DegreeLevel;
use Modules\Catalog\Public\ProgramCatalog;
use Modules\Catalog\Public\ProgramSummary;

uses(RefreshDatabase::class);

/*
| The Catalog\Public ProgramCatalog read facade (ADR-0012) — the one way another
| module resolves a Program by id. It returns read-only ProgramSummary DTOs (never
| the Eloquent model) and null for a missing id; the seam is exercised through the
| bound contract, the module's own Public surface, not Catalog internals.
*/

function programCatalog(): ProgramCatalog
{
    return app(ProgramCatalog::class);
}

it('returns a program summary with its department and faculty names', function () {
    $faculty = Faculty::factory()->create(['name_en' => 'Faculty of Engineering', 'name_ar' => 'كلية الهندسة']);
    $department = Department::factory()->create([
        'faculty_id' => $faculty->id,
        'name_en' => 'Computer Engineering',
        'name_ar' => 'هندسة الحاسوب',
    ]);
    $program = Program::factory()->create([
        'department_id' => $department->id,
        'code' => 'MSC-CE',
        'name_en' => 'MSc in Computer Engineering',
        'name_ar' => 'ماجستير هندسة الحاسوب',
        'degree_level' => DegreeLevel::MSc,
    ]);

    $summary = programCatalog()->find($program->id);

    expect($summary)->toBeInstanceOf(ProgramSummary::class);
    expect($summary->id)->toBe($program->id);
    expect($summary->code)->toBe('MSC-CE');
    expect($summary->nameEn)->toBe('MSc in Computer Engineering');
    expect($summary->nameAr)->toBe('ماجستير هندسة الحاسوب');
    expect($summary->degreeLevel)->toBe(DegreeLevel::MSc);
    expect($summary->departmentNameEn)->toBe('Computer Engineering');
    expect($summary->departmentNameAr)->toBe('هندسة الحاسوب');
    expect($summary->facultyNameEn)->toBe('Faculty of Engineering');
    expect($summary->facultyNameAr)->toBe('كلية الهندسة');
});

it('returns null for a program id that does not exist', function () {
    expect(programCatalog()->find(999_999))->toBeNull();
});

it('resolves several ids at once, keyed by id, omitting the missing ones', function () {
    $a = Program::factory()->create();
    $b = Program::factory()->create();

    $summaries = programCatalog()->findMany([$a->id, $b->id, 999_999]);

    expect(array_keys($summaries))->toEqualCanonicalizing([$a->id, $b->id]);
    expect($summaries[$a->id])->toBeInstanceOf(ProgramSummary::class);
    expect($summaries[$b->id]->id)->toBe($b->id);
});

it('returns an empty array when asked for no ids', function () {
    expect(programCatalog()->findMany([]))->toBe([]);
});
