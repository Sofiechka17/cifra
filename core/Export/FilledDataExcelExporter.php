<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Экспорт одной заполненной таблицы в xlsx по шаблону.
 */
final class FilledDataExcelExporter
{
    /** @var \PgSql\Connection */
    private $conn;
    private TemplateService $service;

    public function __construct($conn, TemplateService $service)
    {
        $this->conn = $conn;
        $this->service = $service;
    }

    public function export(int $filledId): void
    {
        $sql = "
            SELECT f.filled_data, f.filled_date, f.template_id,
                   u.user_full_name, m.municipality_name
              FROM cit_schema.filled_data f
              JOIN cit_schema.users u ON u.user_id = f.user_id
              JOIN cit_schema.municipalities m ON m.municipality_id = f.municipality_id
             WHERE f.filled_data_id = $1
        ";
        $res = pg_query_params($this->conn, $sql, [$filledId]);
        if (!$res || pg_num_rows($res) === 0) {
            throw new RuntimeException('Нет данных для выгрузки.');
        }
        $row = pg_fetch_assoc($res);

        $data = json_decode($row['filled_data'] ?? '[]', true);
        if (!is_array($data)) {
            throw new RuntimeException('Ошибка декодирования JSON данных.');
        }

        $template = $this->service->getTemplateById((int)$row['template_id']);
        $headers = $template->getHeaders();
        $structure = $template->getStructure();
        $rowDefs = $structure['rows'] ?? [];
        $merges = $structure['merges'] ?? [];

        $columnsCount = count($headers);
        if ($columnsCount === 0) {
            throw new RuntimeException('В шаблоне нет заголовков колонок.');
        }

        $rowTypes = [];
        foreach ($rowDefs as $idx => $rowDef) {
            $rowTypes[$idx] = (is_array($rowDef) && ($rowDef['rowType'] ?? 'normal') === 'comment') ? 'comment' : 'normal';
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Отчёт');

        $firstCol = Coordinate::stringFromColumnIndex(1);
        $lastCol = Coordinate::stringFromColumnIndex($columnsCount);

        $sheet->mergeCells($firstCol . '1:' . $lastCol . '1');
        $sheet->setCellValue($firstCol . '1', $template->getName());
        $sheet->getStyle($firstCol . '1')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $sheet->getStyle($firstCol . '1')->getFont()->setBold(true);

        $sheet->mergeCells($firstCol . '2:' . $lastCol . '2');
        $sheet->setCellValue(
            $firstCol . '2',
            "МО: {$row['municipality_name']}   Дата: {$row['filled_date']}"
        );
        $sheet->getStyle($firstCol . '2')->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $headerRow = 4;
        foreach ($headers as $index => $h) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($colLetter . $headerRow, $h['name']);
        }

        $dataStartRow = $headerRow + 1;
        $currentRow = $dataStartRow;

        foreach ($rowDefs as $rIndex => $rowDef) {
            if (is_array($rowDef) && isset($rowDef['rowType'], $rowDef['cells']) && is_array($rowDef['cells'])) {
                $rowType = $rowDef['rowType'];
                $cellsMeta = $rowDef['cells'];
            } else {
                $rowType = 'normal';
                $cellsMeta = is_array($rowDef) ? $rowDef : [];
            }

            $userRow = $data[$rIndex] ?? [];
            if (!is_array($userRow)) $userRow = [];

            if ($rowType === 'comment') {
                $comment = $userRow['Комментарий'] ?? ($cellsMeta['Комментарий'] ?? '');
                $sheet->mergeCells($firstCol . $currentRow . ':' . $lastCol . $currentRow);
                $sheet->setCellValue($firstCol . $currentRow, $comment);
            } else {
                foreach ($headers as $index => $h) {
                    $colLetter = Coordinate::stringFromColumnIndex($index + 1);
                    $name = $h['name'];
                    $value = $userRow[$name] ?? ($cellsMeta[$name] ?? '');
                    $sheet->setCellValue($colLetter . $currentRow, $value);
                }
            }
            $currentRow++;
        }

        if (is_array($merges)) {
            foreach ($merges as $merge) {
                if (!is_array($merge)) continue;
                $sr = (int)($merge['startRow'] ?? 0);
                $sc = (int)($merge['startCol'] ?? 0);
                $rs = (int)($merge['rowSpan'] ?? 1);
                $cs = (int)($merge['colSpan'] ?? 1);
                if ($sr < 0 || $sc < 0 || $rs < 1 || $cs < 1) continue;

                $hasCommentRow = false;
                for ($r = $sr; $r <= $sr + $rs - 1; $r++) {
                    if (($rowTypes[$r] ?? 'normal') === 'comment') { $hasCommentRow = true; break; }
                }
                if ($hasCommentRow) continue;

                $excelRowStart = $dataStartRow + $sr;
                $excelRowEnd = $excelRowStart + $rs - 1;
                $excelColStart = Coordinate::stringFromColumnIndex($sc + 1);
                $excelColEnd = Coordinate::stringFromColumnIndex($sc + $cs);
                $sheet->mergeCells($excelColStart . $excelRowStart . ':' . $excelColEnd . $excelRowEnd);
            }
        }

        $styleArray = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
        ];
        $sheet->getStyle($firstCol . $headerRow . ':' . $lastCol . ($currentRow - 1))->applyFromArray($styleArray);
        $sheet->getStyle($firstCol . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true);

        for ($i = 1; $i <= $columnsCount; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $filename = "filled_data_{$filledId}.xlsx";
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        (new Xlsx($spreadsheet))->save('php://output');
    }
}
