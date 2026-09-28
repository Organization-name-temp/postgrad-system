<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Internal\Models\Department;
use Modules\Catalog\Internal\Models\Faculty;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('resolves exact reference IDs without depending on the first reference page', function (string $resource, string $model) {
    $model::factory()->create(['code' => 'A001']);
    $wanted = $model::factory()->create(['code' => 'Z999']);
    actingAsRole(Role::Student);
    $this->getJson('/api/v1/'.$resource.'?id='.$wanted->id.'&per_page=100')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $wanted->id)->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/'.$resource.'?id=999999')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/'.$resource.'?id=0')->assertUnprocessable();
    $response = $this->get('/api/v1/'.$resource.'/export?id='.$wanted->id.'&per_page=1')->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(2)->and($rows[1][0])->toBe((string) $wanted->id);
    $audit = AuditLog::query()->where('action', 'catalog.'.$resource.'_exported')->sole();
    expect($audit->metadata['criteria']['id'])->toBe([(string) $wanted->id])->and($audit->metadata['row_count'])->toBe(1);
    actingAsRole(Role::Applicant);
    $this->getJson('/api/v1/'.$resource.'?id='.$wanted->id)->assertOk()->assertJsonPath('meta.total', 1);
})->with([
    'Programs' => ['programs', Program::class],
    'Departments' => ['departments', Department::class],
    'Faculties' => ['faculties', Faculty::class],
]);

it('filters and pages a bilingual reference collection with stable ties', function (string $resource, string $model, ?string $parent) {
    $first = $model::factory()->create(['code' => 'A001', 'name_en' => 'Same name', 'name_ar' => 'أَحْمَد', 'is_active' => true]);
    $attributes = ['code' => 'A002', 'name_en' => 'Same name', 'name_ar' => 'أحمد', 'is_active' => true];
    if ($parent !== null) {
        $attributes[$parent] = $first->getAttribute($parent);
    }
    $second = $model::factory()->create($attributes);
    $model::factory()->create(['code' => 'B001', 'name_en' => 'Different', 'name_ar' => 'أحمد', 'is_active' => false]);
    actingAsRole(Role::Student);
    $criteria = '?q='.urlencode('احمد').'&is_active=true&sort=name_en&per_page=1';
    if ($parent !== null) {
        $criteria .= '&'.$parent.'='.$first->getAttribute($parent);
    }
    $this->getJson('/api/v1/'.$resource.$criteria)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $first->id);
    $this->getJson('/api/v1/'.$resource.$criteria.'&page=2')->assertOk()->assertJsonPath('data.0.id', $second->id);
    $this->getJson('/api/v1/'.$resource.'?per_page=5000')->assertOk()->assertJsonPath('meta.per_page', 100)->assertJsonPath('meta.total', 3);
    $this->getJson('/api/v1/'.$resource.'?unknown=1')->assertUnprocessable();
})->with([
    'Programs' => ['programs', Program::class, 'department_id'],
    'Departments' => ['departments', Department::class, 'faculty_id'],
    'Faculties' => ['faculties', Faculty::class, null],
]);

it('exports every matching reference entity with canonical localized fields and an audit', function (string $resource, string $model) {
    $model::factory()->create(['code' => 'A001', 'name_en' => 'Reference one']);
    $model::factory()->create(['code' => 'A002', 'name_en' => 'Reference two']);
    $actor = actingAsRole(Role::Student);
    $response = $this->get('/api/v1/'.$resource.'/export?q=A00&per_page=1', ['Accept-Language' => 'ar'])->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(3)->and($rows[0])->toContain('الاسم بالعربية')->and($rows[1])->toContain('A001');
    $audit = AuditLog::query()->where('action', 'catalog.'.$resource.'_exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)->and($audit->metadata['row_count'])->toBe(2);
})->with([
    'Programs' => ['programs', Program::class],
    'Departments' => ['departments', Department::class],
    'Faculties' => ['faculties', Faculty::class],
]);
