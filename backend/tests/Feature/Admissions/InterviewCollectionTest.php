<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\Interview;
use Modules\Admissions\Internal\Models\InterviewSlot;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('filters booked interviews by bilingual applicant and confirmation state before stable pages', function () {
    $first = Interview::factory()->confirmed()->create(['application_id' => Application::factory()->interviewScheduled()->create(['name_ar' => 'أحمد متقدم'])->id]);
    $second = Interview::factory()->confirmed()->create(['application_id' => Application::factory()->interviewScheduled()->create(['name_ar' => 'احمد متقدم'])->id]);
    Interview::factory()->create(['application_id' => Application::factory()->interviewScheduled()->create(['name_ar' => 'احمد متقدم'])->id]);
    actingAsRole(Role::StudentAffairs);
    $criteria = '?q='.urlencode('احمد').'&status=confirmed&sort=scheduled_at,-id&per_page=1';
    $this->getJson('/api/v1/interviews'.$criteria)->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $second->id)->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/interviews'.$criteria.'&page=2')->assertOk()->assertJsonPath('data.0.id', $first->id);
});

it('keeps assigned interview criteria inside live interviewer scope while administrators see every row', function () {
    $actor = actingAsRole(Role::Instructor);
    $slot = InterviewSlot::factory()->create(['interviewer_id' => $actor->id]);
    $mine = Interview::factory()->confirmed()->create(['interview_slot_id' => $slot->id]);
    $outsideSlot = InterviewSlot::factory()->create();
    Interview::factory()->confirmed()->create(['interview_slot_id' => $outsideSlot->id]);
    $this->getJson('/api/v1/interviews/assigned?status=confirmed&per_page=1')->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $mine->id);
    $this->getJson('/api/v1/interviews/assigned?interviewer_id='.$outsideSlot->interviewer_id)->assertOk()->assertJsonPath('meta.total', 0);
    $slot->update(['interviewer_id' => $outsideSlot->interviewer_id]);
    $this->getJson('/api/v1/interviews/assigned')->assertOk()->assertJsonPath('meta.total', 0);
    actingAsRole(Role::SystemAdmin);
    $this->getJson('/api/v1/interviews/assigned?status=confirmed')->assertOk()->assertJsonPath('meta.total', 2);
});

it('exports the complete mirrored interview scope with text identifiers and Arabic labels', function (string $endpoint, int $expected) {
    $actor = actingAsRole(Role::Instructor);
    foreach (range(1, 2) as $index) {
        Interview::factory()->confirmed()->create([
            'interview_slot_id' => InterviewSlot::factory()->create(['interviewer_id' => $actor->id])->id,
            'application_id' => Application::factory()->interviewScheduled()->create(['name_en' => 'Export '.$index, 'national_id' => '2900000000000'.$index])->id,
        ]);
    }
    Interview::factory()->confirmed()->create(['application_id' => Application::factory()->interviewScheduled()->create(['name_en' => 'Export outside'])->id]);
    if ($endpoint === '/api/v1/interviews') {
        $actor = actingAsRole(Role::StudentAffairs);
    }
    $response = $this->get($endpoint.'/export?q=Export&status=confirmed&sort=id&per_page=1', ['Accept-Language' => 'ar'])->assertOk();
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
    expect($rows)->toHaveCount($expected + 1)->and($rows[0])->toContain('معرّف المقابلة')
        ->and($rows[1][0])->toBeString()->and($rows[1])->toContain('29000000000001');
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('rightToLeft="true"');
    $zip->close();
    $audit = AuditLog::query()->where('action', 'admissions.interviews_exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)->and($audit->metadata['row_count'])->toBe($expected)
        ->and($audit->metadata['resource'])->toBe(substr($endpoint, 8))->and($audit->metadata['criteria']['q'])->toBe('Export');
    $this->get($endpoint.'/export?columns=national_id')->assertUnprocessable();
})->with([['/api/v1/interviews', 3], ['/api/v1/interviews/assigned', 2]]);

it('denies booked interview reads and exports without scheduling permission while own assignments remain empty', function () {
    actingAsRole(Role::Applicant);
    Interview::factory()->confirmed()->create();
    $this->getJson('/api/v1/interviews')->assertForbidden();
    $this->getJson('/api/v1/interviews/export')->assertForbidden();
    $this->getJson('/api/v1/interviews/assigned')->assertOk()->assertJsonPath('meta.total', 0);
    $this->get('/api/v1/interviews/assigned/export')->assertOk();
});
