<?php
/**
 * JSON-эндпоинт: список МО для select-ов.
 */
require_once __DIR__ . '/bootstrap.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

$res = pg_query($conn, 'SELECT municipality_id, municipality_name FROM cit_schema.municipalities ORDER BY municipality_name');
if (!$res) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка загрузки данных.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$list = [];
while ($row = pg_fetch_assoc($res)) {
    $list[] = ['id' => (int)$row['municipality_id'], 'name' => $row['municipality_name']];
}

echo json_encode(['success' => true, 'municipalities' => $list], JSON_UNESCAPED_UNICODE);
