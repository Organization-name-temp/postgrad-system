<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AcademicStanding\Internal\Models\TerminationCase;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\Student;
use Modules\Identity\Internal\Models\User;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('exports every scoped Case beyond one page with localized stages string IDs and both count audits', function (string $locale, string $heading, string $stage) {
    $actor = User::factory()->create();
    $actor->assignRole(Role::Advisor);
    $program = Program::factory()->create();
    $students = Student::factory()->count(30)->create(['advisor_id' => $actor->id, 'program_id' => $program->id]);
    $students[0]->update(['university_id' => '0001-STUDENT']);
    $cases = [];
    foreach ($students as $student) {
        $cases[] = TerminationCase::query()->create(['student_id' => $student->id, 'fingerprint' => str_pad((string) $student->id, 64, 'e'), 'state' => 'AwaitingCouncil', 'evidence' => ['private' => 'HIDDEN-EVIDENCE'], 'department_head_reason' => 'PRIVATE-NARRATIVE']);
    }
    $hidden = Student::factory()->create(['program_id' => $program->id]);
    TerminationCase::query()->create(['student_id' => $hidden->id, 'fingerprint' => str_repeat('f', 64), 'state' => 'AwaitingCouncil', 'evidence' => []]);
    Sanctum::actingAs($actor);
    $response = $this->withHeaders(['Accept-Language' => $locale])->get('/api/v1/academic-standing/reports/termination/cases/export?state=AwaitingCouncil&per_page=1')->assertOk();
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
    expect($rows)->toHaveCount(31)->and($rows[0][0])->toBe($heading)
        ->and($rows[1][0])->toBe((string) $cases[0]->id)
        ->and($rows[1][1])->toBe((string) $students[0]->id)
        ->and($rows[1][2])->toBe('0001-STUDENT')
        ->and($rows[1][9])->toBe($stage)
        ->and(json_encode($rows))->not->toContain('HIDDEN-EVIDENCE', 'PRIVATE-NARRATIVE', $hidden->national_id, $students[0]->national_id);
    $zip = new ZipArchive;
    $zip->open($path);
    $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($locale === 'ar') {
        $document = simplexml_load_string($xml);
        expect((string) $document->sheetViews->sheetView['rightToLeft'])->toBeIn(['true', '1']);
    }
    $audit = AuditLog::query()->where('action', 'academic_standing.termination_report.exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)
        ->and($audit->metadata['row_count'])->toBe(30)
        ->and($audit->metadata['student_count'])->toBe(30)
        ->and($audit->metadata['criteria']['lifecycle'])->toBe(['Active', 'Terminated'])
        ->and($audit->metadata['criteria']['history'])->toBe(['default']);
})->with([
    ['en', 'Case ID', 'Recommended: awaiting Council decision'],
    ['ar', 'رقم الحالة', 'توصية بانتظار قرار المجلس'],
]);
