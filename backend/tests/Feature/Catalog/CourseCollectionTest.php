<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Course;
use Modules\Catalog\Internal\Models\Department;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('filters the Course collection by code fragment bilingual names and stored criteria before stable paging', function () {
    $department = Department::factory()->create();
    $first = Course::factory()->create(['code' => 'CSE511', 'name_en' => 'Algorithms', 'name_ar' => 'أَحْمَد', 'department_id' => $department->id, 'level' => 500, 'total_ch' => 3, 'research_type' => 'PBL']);
    $second = Course::factory()->create(['code' => 'CSE512', 'name_en' => 'Algorithms', 'department_id' => $department->id, 'level' => 500, 'total_ch' => 3, 'research_type' => 'PBL']);
    Course::factory()->create(['code' => 'CSE513', 'level' => 600]);
    actingAsRole(Role::Student);
    $criteria = '?q=CSE5&department_id='.$department->id.'&level=500&total_ch_min=3&total_ch_max=3&research_type=PBL&is_active=true&sort=name_en&per_page=1';
    $this->getJson('/api/v1/courses'.$criteria)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $first->id);
    $this->getJson('/api/v1/courses'.$criteria.'&page=2')->assertOk()->assertJsonPath('data.0.id', $second->id);
    $this->getJson('/api/v1/courses?q='.urlencode('احمد').'&per_page=5000')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 100);
    $this->getJson('/api/v1/courses?unknown=1')->assertUnprocessable();
});

it('exports the complete Course selection as a localized canonical importable workbook and records its audit', function () {
    foreach (['CSE511', 'CSE512'] as $code) {
        Course::factory()->create(['code' => $code, 'research_type' => 'PBL']);
    }
    $actor = actingAsRole(Role::Student);
    $response = $this->get('/api/v1/courses/export?q=CSE5&per_page=1', ['Accept-Language' => 'ar'])->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(3)->and($rows[0])->toContain('رمز المقرر')->and($rows[1])->toContain('CSE511', 'التعلّم القائم على المشكلات');
    $audit = AuditLog::query()->where('action', 'catalog.courses_exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)->and($audit->metadata['row_count'])->toBe(2);
    $this->get('/api/v1/courses/export?columns=code')->assertUnprocessable();
});
