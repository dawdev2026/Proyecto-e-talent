<?php
declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function download_xlsx_workbook(string $filename, array $sheets, string $title = 'Reporte'): void
{
    if (!class_exists(Spreadsheet::class)) {
        platform_error(500, 'PhpSpreadsheet es requerido para generar Excel.', [
            'log' => true,
            'chips' => ['Excel', 'Dependencia'],
        ]);
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($tmpPath === false) {
        platform_error(500, 'No se pudo generar el archivo Excel.', [
            'log' => true,
            'chips' => ['Excel', 'Archivo temporal'],
        ]);
    }

    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('e-talent')
        ->setLastModifiedBy('e-talent')
        ->setTitle($title);

    $usedTitles = [];
    foreach (array_values($sheets) as $sheetIndex => $sheetData) {
        $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet($sheetIndex);
        $sheet->setTitle(xlsx_safe_sheet_title((string) ($sheetData['title'] ?? 'Hoja'), $usedTitles));

        $rows = array_values($sheetData['rows'] ?? []);
        foreach ($rows as $rowIndex => $row) {
            foreach (array_values($row) as $columnIndex => $value) {
                $sheet->setCellValueExplicitByColumnAndRow(
                    $columnIndex + 1,
                    $rowIndex + 1,
                    (string) $value,
                    DataType::TYPE_STRING
                );
            }
        }

        if ($rows) {
            $lastColumn = max(array_map(static fn(array $row): int => count($row), $rows));
            $lastRow = count($rows);
            $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, 1)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, 1)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8F0FE');
            $sheet->setAutoFilterByColumnAndRow(1, 1, $lastColumn, max(1, $lastRow));
            $sheet->freezePane('A2');
            for ($column = 1; $column <= $lastColumn; $column++) {
                $width = (array) ($sheetData['columnWidths'] ?? []);
                if (isset($width[$column - 1]) && is_numeric($width[$column - 1])) {
                    $sheet->getColumnDimensionByColumn($column)->setWidth((float) $width[$column - 1]);
                } else {
                    $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
                }
            }
            if (!empty($sheetData['wrapText'])) {
                $sheet->getStyleByColumnAndRow(1, 1, $lastColumn, $lastRow)->getAlignment()->setWrapText(true);
            }
        }

        foreach ((array) ($sheetData['cellStyles'] ?? []) as $coordinate => $style) {
            if (is_array($style) && preg_match('/^[A-Z]+[1-9][0-9]*$/', (string) $coordinate)) {
                $sheet->getStyle((string) $coordinate)->applyFromArray($style);
            }
        }
    }

    $spreadsheet->setActiveSheetIndex(0);

    $writer = new Xlsx($spreadsheet);
    $writer->setPreCalculateFormulas(false);
    $writer->save($tmpPath);
    $spreadsheet->disconnectWorksheets();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($tmpPath));
    readfile($tmpPath);
    @unlink($tmpPath);
    exit;
}

function xlsx_safe_sheet_title(string $title, array &$usedTitles): string
{
    $title = trim(preg_replace('/[\[\]\:\*\?\/\\\\]+/', ' ', $title) ?? '');
    $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');
    $title = $title !== '' ? $title : 'Hoja';
    $base = mb_substr($title, 0, 31);
    $candidate = $base;
    $suffix = 2;

    while (isset($usedTitles[$candidate])) {
        $tail = ' ' . $suffix;
        $candidate = mb_substr($base, 0, 31 - strlen($tail)) . $tail;
        $suffix++;
    }

    $usedTitles[$candidate] = true;
    return $candidate;
}
