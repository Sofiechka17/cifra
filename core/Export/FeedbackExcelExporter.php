<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Экспорт всех заявок обратной связи в xlsx.
 */
final class FeedbackExcelExporter
{
    private FeedbackRepository $repo;

    public function __construct(FeedbackRepository $repo)
    {
        $this->repo = $repo;
    }

    public function export(): void
    {
        $rows = $this->repo->listAll();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $columns = ['ID', 'ФИО', 'Телефон', 'Текст обращения'];
        foreach ($columns as $col => $name) {
            $sheet->setCellValue(chr(65 + $col) . '1', $name);
        }

        $rowNum = 2;
        foreach ($rows as $row) {
            $sheet->setCellValue('A' . $rowNum, ExcelFormulaGuard::sanitize($row['feedback_id']));
            $sheet->setCellValue('B' . $rowNum, ExcelFormulaGuard::sanitize($row['full_name_feedback']));
            $sheet->setCellValue('C' . $rowNum, ExcelFormulaGuard::sanitize($row['phone_feedback']));
            $sheet->setCellValue('D' . $rowNum, ExcelFormulaGuard::sanitize($row['problem_description_feedback']));
            $rowNum++;
        }

        $filename = 'feedback_requests.xlsx';
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"$filename\"");
        header('Cache-Control: max-age=0');

        (new Xlsx($spreadsheet))->save('php://output');
    }
}
