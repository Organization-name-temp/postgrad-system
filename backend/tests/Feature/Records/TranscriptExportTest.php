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

it('renders every Records export in the requesters Arabic language without changing identifiers or amounts', function () {
    [$student, $id] = transcriptAtStage('ReadyForIssuance');
    $preview = $this->postJson("/api/v1/records/requests/{$id}/preview")->assertOk()->json('data');
    $issued = $this->postJson("/api/v1/records/requests/{$id}/issue", ['review_token' => $preview['review_token']])->assertCreated()->json('data');
    Sanctum::actingAs($student->user);
    $this->withHeader('Accept-Language', 'ar');
    $students = recordsExportRows('/api/v1/records/students/export');
    expect($students[0])->toBe(['معرّف الطالب', 'الرقم الجامعي', 'الاسم بالإنجليزية', 'الاسم بالعربية', 'حالة الطالب'])
        ->and($students[1][0])->toBe((string) $student->id)->and($students[1][4])->toBe('نشط');
    $requests = recordsExportRows('/api/v1/records/requests/export');
    expect($requests[0])->toBe(['معرّف الطلب', 'معرّف الطالب', 'التسليم', 'الرسوم', 'العملة', 'الحالة'])
        ->and($requests[1][0])->toBe((string) $id)->and($requests[1][2])->toBe('وصول رقمي')
        ->and($requests[1][3])->toBe('125.50')->and($requests[1][4])->toBe('EGP')->and($requests[1][5])->toBe('مكتمل');
    $issuances = recordsExportRows('/api/v1/records/issuances/export');
    expect($issuances[0])->toBe(['معرّف الإصدار', 'معرّف الطالب', 'مرجع الإصدار', 'الحالة', 'المُصدر', 'وقت الإصدار'])
        ->and($issuances[1][2])->toBe($issued['reference'])->and($issuances[1][3])->toBe('ساري');
});
