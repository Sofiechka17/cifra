<?php
/**
 * Выгрузка одной заполненной таблицы пользователя в Excel
 * на основе шаблона (template_headers + template_structure.rows).
 *
 * Строки с rowType = "comment" в шаблоне выводятся
 * как одна объединённая по ширине строка с текстом комментария.
 */

session_start();
require 'vendor/autoload.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/core/TemplateService.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

// Только админ
try {
    require_admin();
} catch (Throwable $e) {
    die("Доступ запрещён: только администратор может выгружать таблицы.");
}

if (empty($_GET["filled_id"])) {
    die("Не передан filled_id");
}

$filledId = (int)$_GET["filled_id"];

// Данные о заполненной форме и пользователе
$query = "
    SELECT 
        f.filled_data,
        f.filled_date,
        f.template_id,
        u.user_full_name,
        m.municipality_name
    FROM cit_schema.filled_data f
    JOIN cit_schema.users u ON u.user_id = f.user_id
    JOIN cit_schema.municipalities m ON m.municipality_id = f.municipality_id
    WHERE f.filled_data_id = $1
";
$result = pg_query_params($conn, $query, [$filledId]);
if (!$result || pg_num_rows($result) === 0) {
    die("Нет данных для выгрузки");
}

$row = pg_fetch_assoc($result);

// Данные пользователя (JSON) — заполненные ячейки
$data = json_decode($row['filled_data'], true);
if ($data === null) {
    die("Ошибка декодирования JSON данных");
}

$templateId = (int)$row['template_id'];
$service    = new TemplateService($conn);
$template   = $service->getTemplateById($templateId);
$headers    = $template->getHeaders();
$structure  = $template->getStructure();
$rowDefs    = $structure['rows'] ?? [];
$merges     = $structure['merges'] ?? [];

$columnsCount = count($headers);
if ($columnsCount === 0) {
    die("В шаблоне нет заголовков колонок.");
}

// Массив типов строк (normal/comment) по индексу
$rowTypes = [];
foreach ($rowDefs as $idx => $rowDef) {
    if (is_array($rowDef) && array_key_exists('rowType', $rowDef)) {
        $rowTypes[$idx] = $rowDef['rowType'] ?? 'normal';
    } else {
        $rowTypes[$idx] = 'normal';
    }
}

// Создание Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Отчёт");

// Первая строка — название шаблона
$firstColLetter = Coordinate::stringFromColumnIndex(1);
$lastColLetter  = Coordinate::stringFromColumnIndex($columnsCount);
$sheet->mergeCells($firstColLetter . "1:" . $lastColLetter . "1");
$sheet->setCellValue($firstColLetter . "1", $template->getName());
$sheet->getStyle($firstColLetter . "1")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);
$sheet->getStyle($firstColLetter . "1")->getFont()->setBold(true);

// Вторая строка — МО и дата (без ФИО)
$sheet->mergeCells($firstColLetter . "2:" . $lastColLetter . "2");
$sheet->setCellValue(
    $firstColLetter . "2",
    "МО: {$row['municipality_name']}   Дата: {$row['filled_date']}"
);
$sheet->getStyle($firstColLetter . "2")->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

// Строка заголовков колонок
$headerRow = 4;
foreach ($headers as $index => $h) {
    $colLetter = Coordinate::stringFromColumnIndex($index + 1);
    $sheet->setCellValue($colLetter . $headerRow, $h['name']);
}

// Данные
$dataStartRow = $headerRow + 1;
$currentRow   = $dataStartRow;

// $data — это массив строк, индексами 0,1,2,... совпадающими с rowDefs
foreach ($rowDefs as $rIndex => $rowDef) {
    // Распаковываем структуру: новая форма {rowType, cells} или старая
    if (is_array($rowDef)
        && array_key_exists('rowType', $rowDef)
        && array_key_exists('cells', $rowDef)
        && is_array($rowDef['cells'])
    ) {
        $rowType   = $rowDef['rowType'] ?? 'normal';
        $cellsMeta = $rowDef['cells'];
    } else {
        $rowType   = 'normal';
        $cellsMeta = is_array($rowDef) ? $rowDef : [];
    }

    $userRow = $data[$rIndex] ?? [];
    if (!is_array($userRow)) {
        $userRow = [];
    }

    if ($rowType === 'comment') {
        // Строка комментария: объединяем по всей ширине
        $commentText = '';
        if (isset($userRow['Комментарий'])) {
            $commentText = $userRow['Комментарий'];
        } elseif (isset($cellsMeta['Комментарий'])) {
            $commentText = $cellsMeta['Комментарий'];
        }

        $sheet->mergeCells(
            $firstColLetter . $currentRow . ':' . $lastColLetter . $currentRow
        );
        $sheet->setCellValue($firstColLetter . $currentRow, $commentText);
    } else {
        // Обычная строка: заполняем по заголовкам
        foreach ($headers as $index => $h) {
            $colLetter = Coordinate::stringFromColumnIndex($index + 1);
            $name      = $h['name'];

            // приоритет: заполненные пользователем данные, потом дефолт из шаблона
            $value = $userRow[$name] ?? ($cellsMeta[$name] ?? '');
            $sheet->setCellValue($colLetter . $currentRow, $value);
        }
    }

    $currentRow++;
}

// Применяем объединения merges (кроме строк-комментариев)
if (is_array($merges)) {
    foreach ($merges as $merge) {
        if (!is_array($merge)) {
            continue;
        }

        $sr = isset($merge['startRow']) ? (int)$merge['startRow'] : 0;
        $sc = isset($merge['startCol']) ? (int)$merge['startCol'] : 0;
        $rs = isset($merge['rowSpan']) ? (int)$merge['rowSpan'] : 1;
        $cs = isset($merge['colSpan']) ? (int)$merge['colSpan'] : 1;

        if ($sr < 0 || $sc < 0 || $rs < 1 || $cs < 1) {
            continue;
        }

        $startRowIndex = $sr;
        $endRowIndex   = $sr + $rs - 1;

        // Если в диапазоне есть строка-комментарий — пропускаем такое объединение
        $hasCommentRow = false;
        for ($r = $startRowIndex; $r <= $endRowIndex; $r++) {
            $type = $rowTypes[$r] ?? 'normal';
            if ($type === 'comment') {
                $hasCommentRow = true;
                break;
            }
        }
        if ($hasCommentRow) {
            continue;
        }

        // Переводим координаты "шаблона" в координаты Excel
        $excelRowStart = $dataStartRow + $startRowIndex;
        $excelRowEnd   = $excelRowStart + $rs - 1;

        $excelColStart = Coordinate::stringFromColumnIndex($sc + 1);
        $excelColEnd   = Coordinate::stringFromColumnIndex($sc + $cs);

        $sheet->mergeCells($excelColStart . $excelRowStart . ':' . $excelColEnd . $excelRowEnd);
    }
}

// Стилизация: рамки и выравнивание
$styleArray = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true
    ]
];

$sheet->getStyle(
    $firstColLetter . $headerRow . ':' . $lastColLetter . ($currentRow - 1)
)->applyFromArray($styleArray);

// Жирная шапка
$sheet->getStyle(
    $firstColLetter . $headerRow . ':' . $lastColLetter . $headerRow
)->getFont()->setBold(true);

// Автоширина
for ($i = 1; $i <= $columnsCount; $i++) {
    $colLetter = Coordinate::stringFromColumnIndex($i);
    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
}

// Выгрузка
$filename = "filled_data_{$filledId}.xlsx";
if (ob_get_length()) {
    ob_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;