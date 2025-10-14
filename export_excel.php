<?php
session_start();
require 'vendor/autoload.php';
include "db.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

if (empty($_SESSION["is_admin"])) die("Доступ запрещён");
if (empty($_GET["filled_id"])) die("Не передан filled_id");

$filledId = intval($_GET["filled_id"]);

// Данные о заполненной форме и пользователе
$query = "SELECT f.filled_data, f.filled_date, u.user_full_name, m.municipality_name
          FROM cit_schema.filled_data f
          JOIN cit_schema.users u ON u.user_id = f.user_id
          JOIN cit_schema.municipalities m ON m.municipality_id = f.municipality_id
          WHERE f.filled_data_id = $1";
$result = pg_query_params($conn, $query, [$filledId]);
if (!$result || pg_num_rows($result) === 0) die("Нет данных для выгрузки");

$row = pg_fetch_assoc($result);
$data = json_decode($row['filled_data'], true);
if ($data === null) die("Ошибка декодирования JSON данных");

// Шаблон показателей 
$templateRows = [
    ["Показатели"=>"Численность населения (в среднегодовом исчислении)","Единица измерения"=>"тыс. чел."],
    ["Показатели"=>"Численность населения старше трудоспособного возраста (на 1 января года)","Единица измерения"=>"тыс. чел."],
    ["Показатели"=>"Общий коэффициент рождаемости","Единица измерения"=>"на 1000 чел."],
    ["Показатели"=>"Общий коэффициент смертности","Единица измерения"=>"на 1000 чел."],
    ["Показатели"=>"Коэффициент естественного прироста населения","Единица измерения"=>"на 1000 чел."],
    ["Показатели"=>"Миграционный прирост (убыль)","Единица измерения"=>"тыс. чел."]
];

// Объединенный шаблон и данные пользователя
$filledRows = [];
foreach ($templateRows as $i => $rowTemplate) {
    $filledRows[$i] = $rowTemplate;
    if (isset($data[$i]) && is_array($data[$i])) {
        foreach ($data[$i] as $key => $value) {
            $filledRows[$i][$key] = $value;
        }
    } else {
        // Если пользователь не заполнил, оставляем пустые значения для годов
        foreach (["2022","2023","2024","2025","2026_консервативный","2026_базовый",
                  "2027_консервативный","2027_базовый","2028_консервативный","2028_базовый"] as $y) {
            $filledRows[$i][$y] = "";
        }
    }
}

// Создание Excel 
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Прогноз");

// Шапка
$sheet->mergeCells("A1:L1");
$sheet->setCellValue("A1", "Основные показатели для разработки прогноза социального развития на 2026–2028 гг.");
$sheet->getStyle("A1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
$sheet->getStyle("A1")->getFont()->setBold(true);

$sheet->mergeCells("A2:L2");
$sheet->setCellValue("A2", "ФИО: {$row['user_full_name']}   МО: {$row['municipality_name']}   Дата: {$row['filled_date']}");
$sheet->getStyle("A2")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

// Заголовки таблицы
$startRow = 4;
$sheet->mergeCells("A{$startRow}:A" . ($startRow+2));
$sheet->setCellValue("A{$startRow}", "Показатели");
$sheet->mergeCells("B{$startRow}:B" . ($startRow+2));
$sheet->setCellValue("B{$startRow}", "Единица измерения");
$sheet->mergeCells("C{$startRow}:F{$startRow}");
$sheet->setCellValue("C{$startRow}", "Отчет");
$sheet->mergeCells("G{$startRow}:L{$startRow}");
$sheet->setCellValue("G{$startRow}", "Прогноз");

// Второй уровень
$sheet->setCellValue("C" . ($startRow+1), "2022");
$sheet->setCellValue("D" . ($startRow+1), "2023");
$sheet->setCellValue("E" . ($startRow+1), "2024");
$sheet->setCellValue("F" . ($startRow+1), "2025");
$sheet->setCellValue("G" . ($startRow+1), "2026 (консервативный)");
$sheet->setCellValue("H" . ($startRow+1), "2026 (базовый)");
$sheet->setCellValue("I" . ($startRow+1), "2027 (консервативный)");
$sheet->setCellValue("J" . ($startRow+1), "2027 (базовый)");
$sheet->setCellValue("K" . ($startRow+1), "2028 (консервативный)");
$sheet->setCellValue("L" . ($startRow+1), "2028 (базовый)");

// Данные
$rowNum = $startRow + 2;
foreach ($filledRows as $entry) {
    $sheet->setCellValue("A{$rowNum}", $entry["Показатели"]);
    $sheet->setCellValue("B{$rowNum}", $entry["Единица измерения"]);
    $sheet->setCellValue("C{$rowNum}", $entry["2022"]);
    $sheet->setCellValue("D{$rowNum}", $entry["2023"]);
    $sheet->setCellValue("E{$rowNum}", $entry["2024"]);
    $sheet->setCellValue("F{$rowNum}", $entry["2025"]);
    $sheet->setCellValue("G{$rowNum}", $entry["2026_консервативный"]);
    $sheet->setCellValue("H{$rowNum}", $entry["2026_базовый"]);
    $sheet->setCellValue("I{$rowNum}", $entry["2027_консервативный"]);
    $sheet->setCellValue("J{$rowNum}", $entry["2027_базовый"]);
    $sheet->setCellValue("K{$rowNum}", $entry["2028_консервативный"]);
    $sheet->setCellValue("L{$rowNum}", $entry["2028_базовый"]);
    $rowNum++;
}

// Стилизация
$styleArray = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER,'vertical' => Alignment::VERTICAL_CENTER,'wrapText' => true]
];
$sheet->getStyle("A{$startRow}:L" . ($rowNum-1))->applyFromArray($styleArray);
$sheet->getStyle("A{$startRow}:L" . ($startRow+2))->getFont()->setBold(true);

// Автоширина
$sheet->getColumnDimension("A")->setWidth(40);
$sheet->getColumnDimension("B")->setWidth(20);
foreach (range('C','L') as $col) $sheet->getColumnDimension($col)->setWidth(15);

// Выгрузка
$filename = "filled_data_{$filledId}.xlsx";
if (ob_get_length()) ob_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
?>
