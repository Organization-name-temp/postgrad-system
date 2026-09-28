<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Enums\IntakeTerm;
use Modules\Admissions\Internal\Models\AdmissionCycle;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('filters bilingual cycle names and window state before stable pages', function () {
    $first = AdmissionCycle::factory()->open()->create(['year' => 2026, 'intake_term' => IntakeTerm::Fall, 'name_ar' => 'دَورة أحمد']);
    $second = AdmissionCycle::factory()->open()->create(['year' => 2026, 'intake_term' => IntakeTerm::Spring, 'name_ar' => 'دورة احمد']);
    AdmissionCycle::factory()->past()->create(['year' => 2025, 'name_ar' => 'دورة احمد']);
    actingAsRole(Role::StudentAffairs);

    $criteria = '?q='.urlencode('احمد').'&is_open=true&year_min=2026&sort=year&per_page=1';
    $this->getJson('/api/v1/admission-cycles'.$criteria)->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first->id)->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/admission-cycles'.$criteria.'&page=2')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->id)->assertJsonPath('meta.current_page', 2);
    $this->getJson('/api/v1/admission-cycles?is_open=false')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.is_open', false);
});

it('keeps public lookup criteria inside currently open cycles with the reference default', function () {
    $open = AdmissionCycle::factory()->open()->create(['name_en' => 'Open intake']);
    $future = AdmissionCycle::factory()->future()->create(['name_en' => 'Open intake future']);
    AdmissionCycle::factory()->closed()->create(['name_en' => 'Open intake inactive']);
    $this->getJson('/api/v1/admission-cycles/open?q=intake')->assertOk()
        ->assertJsonPath('meta.per_page', 100)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $open->id);
    $this->getJson('/api/v1/admission-cycles/open?id='.$future->id)->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/admission-cycles/open?is_open=false')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/admission-cycles/open?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 100);
    $this->getJson('/api/v1/admission-cycles/open?unknown_field=1')->assertUnprocessable();
    $this->getJson('/api/v1/admission-cycles/open/export')->assertNotFound();
});

it('exports all matching cycle rows with bilingual window labels and an audit entry', function () {
    $first = AdmissionCycle::factory()->open()->create(['year' => 2026, 'intake_term' => IntakeTerm::Fall]);
    $second = AdmissionCycle::factory()->open()->create(['year' => 2026, 'intake_term' => IntakeTerm::Spring]);
    AdmissionCycle::factory()->closed()->create(['year' => 2025]);
    $actor = actingAsRole(Role::StudentAffairs);
    $response = $this->get('/api/v1/admission-cycles/export?year=2026&sort=id&per_page=1', ['Accept-Language' => 'ar'])->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    $reader = new Reader;
    $reader->open($path);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(3)->and($rows[0])->toContain('معرّف دورة القبول')->and($rows[1][0])->toBe((string) $first->id)
        ->and($rows[2][0])->toBe((string) $second->id)->and($rows[1])->toContain('مفتوحة');
    $audit = AuditLog::query()->where('action', 'admissions.cycles_exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)->and($audit->metadata['row_count'])->toBe(2)->and($audit->metadata['criteria']['year'])->toBe(['2026']);
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('rightToLeft="true"');
    $zip->close();
    actingAsRole(Role::Applicant);
    $this->get('/api/v1/admission-cycles/export')->assertForbidden();
});
