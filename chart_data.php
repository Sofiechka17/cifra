<?php
/**
 * JSON-данные одной заполненной таблицы для построения графика.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin() && !is_minec()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён'], JSON_UNESCAPED_UNICODE);
    exit;
}

$filledId = (int)($_GET['filled_id'] ?? 0);
if ($filledId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не передан filled_id'], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
    SELECT f.filled_data, f.filled_date, f.template_id,
           t.template_name, t.template_headers, m.municipality_name
      FROM cit_schema.filled_data f
      JOIN cit_schema.table_templates t ON t.template_id = f.template_id
      JOIN cit_schema.municipalities m ON m.municipality_id = f.municipality_id
     WHERE f.filled_data_id = $1
     LIMIT 1
";
$res = pg_query_params($conn, $sql, [$filledId]);
if (!$res || pg_num_rows($res) === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Данные не найдены'], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = pg_fetch_assoc($res);
echo json_encode([
    'success' => true,
    'template_name' => $row['template_name'],
    'municipality_name' => $row['municipality_name'],
    'filled_date' => $row['filled_date'],
    'headers' => json_decode($row['template_headers'] ?? '[]', true) ?? [],
    'filled_data' => json_decode($row['filled_data'] ?? '[]', true) ?? [],
], JSON_UNESCAPED_UNICODE);
