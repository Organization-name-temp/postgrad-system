<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admissions\Internal\Enums\ApplicationStatus;
use Modules\Admissions\Internal\Models\Application;
use Modules\Admissions\Internal\Models\ApplicationStatusTransition;
use Modules\Catalog\Internal\Models\Program;
use Modules\Identity\Internal\Models\AuditLog;
use Modules\Identity\Internal\Models\FacultyDeanAssignment;
use Modules\Identity\Public\Enums\Role;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

it('exports the full filtered screening population as localized text identifiers and audits its disclosure', function () {
    $program = Program::factory()->create();
    foreach (range(1, 3) as $index) {
        Application::factory()->underReview()->create(['primary_program_id' => $program->id, 'name_en' => 'Export '.$index, 'national_id' => '2900000000000'.$index]);
    }
    $actor = actingAsRole(Role::StudentAffairs);
    $response = $this->get('/api/v1/applications/export?q=Export&per_page=1', ['Accept-Language' => 'ar'])->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();
    $reader = new Reader;
    $reader->open($path);
    $values = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $values[] = $row->toArray();
        }
    }
    $reader->close();
    expect($values)->toHaveCount(4)->and($values[0])->toContain('الرقم القومي / جواز السفر')->and($values[1])->toContain('29000000000001');
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('rightToLeft="true"');
    $zip->close();
    $audit = AuditLog::query()->where('action', 'admissions.applications_exported')->sole();
    expect($audit->actor_user_id)->toBe($actor->id)->and($audit->metadata['row_count'])->toBe(3)->and($audit->metadata['criteria']['q'])->toBe('Export');
    actingAsRole(Role::Applicant);
    $this->get('/api/v1/applications/export')->assertForbidden();
});

it('exports the same derived filtered and ordered Dean scope as the queue', function () {
    $insideProgram = Program::factory()->create();
    $outsideProgram = Program::factory()->create();
    $old = Application::factory()->underReview()->create(['primary_program_id' => $insideProgram->id, 'national_id' => '29000000000001']);
    $young = Application::factory()->underReview()->create(['primary_program_id' => $insideProgram->id]);
    $outside = Application::factory()->underReview()->create(['primary_program_id' => $outsideProgram->id]);
    foreach ([[$old, 9], [$young, 2], [$outside, 12]] as [$application, $age]) {
        ApplicationStatusTransition::factory()
            ->enteredAt(ApplicationStatus::UnderSAReview, now()->subDays($age))
            ->create(['application_id' => $application->id]);
    }
    $actor = actingAsRole(Role::Dean);
    FacultyDeanAssignment::create(['user_id' => $actor->id, 'faculty_id' => $insideProgram->department->faculty_id]);
    $criteria = '?age_days_min=7&sort=-age_days&per_page=1';
    $this->getJson('/api/v1/applications'.$criteria)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $old->id);
    $response = $this->get('/api/v1/applications/export'.$criteria)->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();
    expect($rows)->toHaveCount(2)->and($rows[0])->toContain('National ID / passport')
        ->and($rows[1][0])->toBe((string) $old->id)->and($rows[1])->toContain('Under Student Affairs review');
    $this->get('/api/v1/applications/export?columns=national_id')->assertUnprocessable();
});
