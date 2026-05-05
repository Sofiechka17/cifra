<?php

require_once __DIR__ . '/bootstrap.php';

(new SessionGuard())->requireAuth();

header('Content-Type: application/json; charset=utf-8');

$res = pg_query($conn, 'SELECT municipality_id, municipality_name FROM cit_schema.municipalities ORDER BY municipality_name');
if (!$res) {
    JsonResponse::error(500, 'Ошибка загрузки данных.');
    exit;
}

$list = [];
while ($row = pg_fetch_assoc($res)) {
    $list[] = ['id' => (int)$row['municipality_id'], 'name' => $row['municipality_name']];
}

JsonResponse::success('', ['municipalities' => $list]);
