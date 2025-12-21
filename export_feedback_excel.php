<?php
/**
 * Выгрузка всех заявок обратной связи в Excel 
 */
session_start();
require 'vendor/autoload.php';
include "db.php";

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (empty($_SESSION["is_admin"])) {
    die("Доступ запрещён");
}

// Получаем все заявки обратной связи
$query = "
    SELECT fr.feedback_id, 
           fr.full_name_feedback, 
           fr.phone_feedback, 
           fr.problem_description_feedback
    FROM cit_schema.feedback_requests fr
    ORDER BY fr.feedback_id DESC
";
$result = pg_query($conn, $query);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Заголовки
$headers = ["ID", "ФИО", "Телефон", "Текст обращения"];
foreach ($headers as $col => $header) {
    $sheet->setCellValue(chr(65 + $col) . "1", $header);
}

// Данные
$rowNum = 2;
while ($row = pg_fetch_assoc($result)) {
    $sheet->setCellValue("A" . $rowNum, $row["feedback_id"]);
    $sheet->setCellValue("B" . $rowNum, $row["full_name_feedback"]);
    $sheet->setCellValue("C" . $rowNum, $row["phone_feedback"]);
    $sheet->setCellValue("D" . $rowNum, $row["problem_description_feedback"]);
    $rowNum++;
}

// Выгрузка в файл
$filename = "feedback_requests.xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save("php://output");
exit;
