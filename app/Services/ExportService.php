<?php
namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExportService
{
    public static function excel(string $title, string $subtitle, array $result, string $filename): never
    {
        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $title), 0, 31));
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValueExplicit('A2', $subtitle, DataType::TYPE_STRING);

        $row = 4;
        foreach ($result['columns'] as $i => $c) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $row, $c['label']);
        }
        $lastCol = Coordinate::stringFromColumnIndex(count($result['columns']));
        $sheet->getStyle("A$row:$lastCol$row")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A$row:$lastCol$row")->getFill()->setFillType('solid')->getStartColor()->setRGB('12382B');

        foreach ($result['rows'] as $r) {
            $row++;
            foreach ($result['columns'] as $i => $c) {
                self::put($sheet, Coordinate::stringFromColumnIndex($i + 1) . $row, $c, $r[$c['key']] ?? null);
            }
        }
        if ($result['totals']) {
            $row++;
            $sheet->setCellValue("A$row", 'Total');
            foreach ($result['columns'] as $i => $c) {
                if (isset($result['totals'][$c['key']])) {
                    self::put($sheet, Coordinate::stringFromColumnIndex($i + 1) . $row, $c, $result['totals'][$c['key']]);
                }
            }
            $sheet->getStyle("A$row:$lastCol$row")->getFont()->setBold(true);
        }
        foreach ($result['columns'] as $i => $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setAutoSize(true);
        }
        $sheet->freezePane('A5');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        (new Xlsx($book))->save('php://output');
        exit;
    }

    private static function put($sheet, string $cell, array $c, mixed $v): void
    {
        $type = $c['type'] ?? 'text';
        if ($v === null || $v === '') {
            return;
        }
        if (in_array($type, ['money', 'number', 'decimal'], true)) {
            $sheet->setCellValueExplicit($cell, (float) $v, DataType::TYPE_NUMERIC);
            if ($type !== 'number') {
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
            }
            return;
        }
        // Always text, so a value such as "=SUM(...)" typed by a user can never run as a formula.
        $text = $type === 'date' ? fdate($v) : ($type === 'label' ? label_of($v) : (string) $v);
        $sheet->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);
    }
}
