<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('filters normalized bilingual slot locations and derived availability before stable paging', function () {
    $first = InterviewSlot::factory()->create(['location' => 'أحمد قاعة', 'scheduled_at' => now()->addDays(3)->startOfDay()]);
    $second = InterviewSlot::factory()->create(['location' => 'احمد قاعة', 'scheduled_at' => $first->scheduled_at]);
    $booked = InterviewSlot::factory()->create(['location' => 'احمد قاعة']);
    Interview::factory()->create(['interview_slot_id' => $booked->id]);
    actingAsRole(Role::StudentAffairs);
    $criteria = '?q='.urlencode('احمد').'&is_open=true&sort=scheduled_at&per_page=1';
    $this->getJson('/api/v1/interview-slots'.$criteria)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.id', $first->id);
    $this->getJson('/api/v1/interview-slots'.$criteria.'&page=2')->assertOk()->assertJsonPath('data.0.id', $second->id);
});

it('keeps the personal slot picker inside currently open slots with the reference page default', function () {
    $open = InterviewSlot::factory()->create(['location' => 'Target room']);
    $booked = InterviewSlot::factory()->create(['location' => 'Target room']);
    Interview::factory()->create(['interview_slot_id' => $booked->id]);
    actingAsRole(Role::Applicant);
    $this->getJson('/api/v1/application/interview/slots?q=Target&per_page=1')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $open->id);
    $this->getJson('/api/v1/application/interview/slots')->assertOk()->assertJsonPath('meta.per_page', 100);
    $this->getJson('/api/v1/application/interview/slots?is_open=false')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson('/api/v1/application/interview/slots?unknown=1')->assertUnprocessable();
    $this->getJson('/api/v1/application/interview/slots/export')->assertNotFound();
});

it('exports all filtered manager-readable slots with localized labels and an audit count', function () {
    $first = InterviewSlot::factory()->create(['location' => 'Export room']);
    InterviewSlot::factory()->create(['location' => 'Export room']);
    $booked = InterviewSlot::factory()->create(['location' => 'Export room']);
    Interview::factory()->create(['interview_slot_id' => $booked->id]);
    $actor = actingAsRole(Role::StudentAffairs);
    $response = $this->get('/api/v1/interview-slots/export?q=Export&is_open=true&sort=id&per_page=1', ['Accept-Language' => 'ar'])->assertOk();
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
    expect($rows)->toHaveCount(3)->and($rows[0])->toContain('معرّف الموعد')
        ->and($rows[1][0])->toBe((string) $first->id);
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('rightToLeft="true"');
    $zip->close();
    $audit = AuditLog::query()->where('action', 'admissions.interview_slots_exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)->and($audit->metadata['row_count'])->toBe(2)
        ->and($audit->metadata['criteria']['q'])->toBe('Export');
    $this->get('/api/v1/interview-slots/export?columns=interviewer_id')->assertUnprocessable();
    actingAsRole(Role::Applicant);
    $this->getJson('/api/v1/interview-slots/export')->assertForbidden();
});
