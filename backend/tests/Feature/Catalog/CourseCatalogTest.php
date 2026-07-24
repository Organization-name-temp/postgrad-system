<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Enums\ResearchType;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Public\CourseCatalog;
use Modules\Catalog\Public\CourseSummary;
use Modules\Catalog\Public\Enums\CourseLevel;

uses(RefreshDatabase::class);

/*
| The Catalog\Public CourseCatalog read facade (ADR-0012, ADR-0010's deferred
| Course reader) — the one way another module resolves a Course by id. It returns
| read-only CourseSummary DTOs (never the Eloquent model) and null for a missing id;
| the seam is exercised through the bound contract, the module's own Public surface,
| not Catalog internals. Enrollment (ticket 03) is its first consumer.
*/

function courseCatalog(): CourseCatalog
{
    return app(CourseCatalog::class);
}

it('returns a course summary with its code, names, level, total CH and active flag', function () {
    $course = Course::factory()->create([
        'code' => 'DEP501',
        'name_en' => 'Advanced Algorithms',
        'name_ar' => 'خوارزميات متقدمة',
        'level' => CourseLevel::Master,
        'lecture_ch' => 2,
        'tutorial_ch' => 1,
        'lab_ch' => 0,
        'total_ch' => 3,
        'is_active' => true,
    ]);

    $summary = courseCatalog()->find($course->id);

    expect($summary)->toBeInstanceOf(CourseSummary::class)
        ->and($summary->id)->toBe($course->id)
        ->and($summary->code)->toBe('DEP501')
        ->and($summary->nameEn)->toBe('Advanced Algorithms')
        ->and($summary->nameAr)->toBe('خوارزميات متقدمة')
        ->and($summary->level)->toBe(CourseLevel::Master)
        ->and($summary->totalCh)->toBe(3)
        ->and($summary->researchType)->toBeNull()
        ->and($summary->isActive)->toBeTrue();
});

it('exposes the research type as its plain string value, never the internal enum', function () {
    $course = Course::factory()->withResearchType(ResearchType::Pbl)->create();

    $summary = courseCatalog()->find($course->id);

    expect($summary->researchType)->toBe('PBL');
});

it('reflects an inactive course', function () {
    $course = Course::factory()->inactive()->create();

    expect(courseCatalog()->find($course->id)->isActive)->toBeFalse();
});

it('returns null for a course id that does not exist', function () {
    expect(courseCatalog()->find(999_999))->toBeNull();
});

it('resolves several ids at once, keyed by id, omitting the missing ones', function () {
    $a = Course::factory()->create();
    $b = Course::factory()->create();

    $summaries = courseCatalog()->findMany([$a->id, $b->id, 999_999]);

    expect(array_keys($summaries))->toEqualCanonicalizing([$a->id, $b->id])
        ->and($summaries[$a->id])->toBeInstanceOf(CourseSummary::class)
        ->and($summaries[$b->id]->id)->toBe($b->id);
});

it('returns an empty array when asked for no ids', function () {
    expect(courseCatalog()->findMany([]))->toBe([]);
});
