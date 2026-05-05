<?php

require_once __DIR__ . '/bootstrap.php';

$guard = new SessionGuard();
if (!$guard->isAdmin() && !$guard->isMinec()) {
    http_response_code(403);
    exit('Доступ запрещён');
}

$filledId = (int)($_GET['filled_id'] ?? 0);
if ($filledId <= 0) {
    http_response_code(400);
    exit('Не передан filled_id.');
}

$exporter = new FilledDataExcelExporter($conn, new TemplateService($conn));
$exporter->export($filledId);
exit;
