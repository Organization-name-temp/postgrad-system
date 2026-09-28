<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('exports the complete authorized selection with readable labels string IDs and effective audited criteria', function (string $locale, string $heading, string $standing) {
    $actor = User::factory()->create();
    $actor->assignRole(Role::Advisor);
    $program = Program::factory()->create();
    $first = Student::factory()->create([
        'advisor_id' => $actor->id, 'program_id' => $program->id,
        'university_id' => '0001-STUDENT', 'name_en' => 'Visible Student',
    ]);
    $second = Student::factory()->create(['advisor_id' => $actor->id, 'program_id' => $program->id]);
    $hidden = Student::factory()->create(['program_id' => $program->id]);
    Sanctum::actingAs($actor);
    $response = $this->withHeaders(['Accept-Language' => $locale])
        ->get('/api/v1/academic-standing/reports/standing/students/export?program_id='.$program->id.'&standing=NotYetAssessed&per_page=1')
        ->assertOk();
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
    expect($rows)->toHaveCount(3)
        ->and($rows[0][0])->toBe($heading)
        ->and($rows[1][0])->toBe((string) $first->id)
        ->and($rows[1][1])->toBe('0001-STUDENT')
        ->and($rows[1][9])->toBe($standing)
        ->and($rows[2][0])->toBe((string) $second->id);
    foreach ([$hidden->national_id, $first->national_id, $second->national_id] as $privateId) {
        expect(json_encode($rows))->not->toContain($privateId);
    }
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($locale === 'ar') {
        $document = simplexml_load_string($xml);
        expect((string) $document->sheetViews->sheetView['rightToLeft'])->toBeIn(['true', '1']);
    }
    $audit = AuditLog::query()->where('action', 'academic_standing.report.exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)
        ->and($audit->metadata['row_count'])->toBe(2)
        ->and($audit->metadata['criteria']['program_id'])->toBe([(string) $program->id])
        ->and($audit->metadata['criteria']['lifecycle'])->toBe(['Active'])
        ->and($audit->metadata['criteria']['standing'])->toBe(['NotYetAssessed']);
})->with([
    ['en', 'Student ID', 'Not yet assessed'],
    ['ar', 'معرّف الطالب', 'لم يُقيّم بعد'],
]);
