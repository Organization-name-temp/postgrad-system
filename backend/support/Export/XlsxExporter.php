<?php

namespace Support\Export;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Domain-free true-XLSX writer used by audited module-owned export projections. */
final class XlsxExporter
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<list<bool|float|int|string|null>>  $rows
     */
    public function download(string $filename, array $headers, iterable $rows, bool $rightToLeft = false): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'prs-xlsx-');
        if ($path === false) {
            throw new \RuntimeException('Unable to allocate an export file.');
        }

        $this->writeTo($path, $headers, $rows, $rightToLeft);

        return response()->download(
            $path,
            str_ends_with($filename, '.xlsx') ? $filename : $filename.'.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        )->deleteFileAfterSend();
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<bool|float|int|string|null>>  $rows
     */
    public function writeTo(string $path, array $headers, iterable $rows, bool $rightToLeft = false): void
    {
        $writer = new Writer;
        $writer->openToFile($path);
        if ($rightToLeft) {
            $writer->getCurrentSheet()->setSheetView((new SheetView)->setRightToLeft(true));
        }
        $writer->addRow($this->row($headers));
        foreach ($rows as $values) {
            $writer->addRow($this->row($values));
        }
        $writer->close();
    }

    /** @param list<bool|float|int|string|null> $values */
    private function row(array $values): Row
    {
        return new Row(array_map(
            static fn (bool|float|int|string|null $value): Cell => is_string($value)
                ? new StringCell($value, null)
                : Cell::fromValue($value),
            $values,
        ));
    }
}
