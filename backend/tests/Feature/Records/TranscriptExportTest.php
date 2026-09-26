<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;

uses(RefreshDatabase::class);

function recordsExportRows(string $url): array
{
    $response = test()->get($url)->assertOk();
    $reader = new Reader;
    $reader->open($response->baseResponse->getFile()->getPathname());
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    return $rows;
}

it('exports scoped identifiers as spreadsheet strings and never exports bearer tokens', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $review = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    $issued = $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $review['review_token']])->assertCreated()->json('data');
    Sanctum::actingAs($student->user);
    $students = recordsExportRows('/api/v1/records/students/export');
    expect($students[1][0])->toBe((string) $student->id)->and($students[1][1])->toBe($student->university_id);
    $issuances = recordsExportRows('/api/v1/records/issuances/export');
    expect($issuances[1][0])->toBe((string) $issued['id'])->and($issuances[1][1])->toBe((string) $student->id);
    expect(json_encode($issuances))->not->toContain(basename($issued['verification_url']));
});
