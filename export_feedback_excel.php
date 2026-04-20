<?php

require_once __DIR__ . '/bootstrap.php';

$guard = new SessionGuard();
if (!$guard->isAdmin() && !$guard->isMinec()) {
    http_response_code(403);
    exit('Доступ запрещён');
}

(new FeedbackExcelExporter(new FeedbackRepository($conn)))->export();
exit;
