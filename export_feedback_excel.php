<?php
/**
 * Экспорт всех заявок обратной связи в xlsx.
 */
require_once __DIR__ . '/bootstrap.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!is_admin() && !is_minec()) {
    http_response_code(403);
    exit('Доступ запрещён');
}

$res = pg_query($conn, "
    SELECT feedback_id, full_name_feedback, phone_feedback, problem_description_feedback
      FROM cit_schema.feedback_requests
     ORDER BY feedback_id DESC
");
$rows = [];
if ($res) {
    while ($row = pg_fetch_assoc($res)) {
        $rows[] = $row;
    }
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$columns = ['ID', 'ФИО', 'Телефон', 'Текст обращения'];
foreach ($columns as $col => $name) {
    $sheet->setCellValue(chr(65 + $col) . '1', $name);
}

$rowNum = 2;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $rowNum, $row['feedback_id']);
    $sheet->setCellValue('B' . $rowNum, $row['full_name_feedback']);
    $sheet->setCellValue('C' . $rowNum, $row['phone_feedback']);
    $sheet->setCellValue('D' . $rowNum, $row['problem_description_feedback']);
    $rowNum++;
}

$filename = 'feedback_requests.xlsx';
if (ob_get_length()) ob_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

(new Xlsx($spreadsheet))->save('php://output');
