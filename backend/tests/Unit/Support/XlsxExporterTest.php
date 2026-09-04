<?php

use OpenSpout\Reader\XLSX\Reader;
use Support\Export\XlsxExporter;

it('writes a true RTL xlsx while retaining identifiers and formula-looking input as literal strings', function () {
    $path = tempnam(sys_get_temp_dir(), 'prs-xlsx-test-');
    expect($path)->not->toBeFalse();

    $formulaLookingNote = '=HYPERLINK("https://attacker.invalid","Open")';
    (new XlsxExporter)->writeTo(
        $path,
        ['الرقم القومي', 'الحالة', 'ملاحظة الطالب'],
        [['29001010101010', 'قيد المراجعة', $formulaLookingNote]],
        true,
    );

    $reader = new Reader;
    $reader->open($path);
    $values = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $values[] = $row->toArray();
        }
    }
    $reader->close();
    expect($values)->toBe([
        ['الرقم القومي', 'الحالة', 'ملاحظة الطالب'],
        ['29001010101010', 'قيد المراجعة', $formulaLookingNote],
    ])->and($values[1][0])->toBeString();

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expect($sheetXml)
        ->toContain('rightToLeft="true"')
        ->not->toContain('<f>')
        ->not->toContain('<f ');
    unlink($path);
});
