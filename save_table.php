<?php
/**
 * Контроллер сохранения заполненной таблицы.
 */
require_once __DIR__ . '/bootstrap.php';

require_auth();

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE);
    exit;
}

Csrf::verifyOrFail();

$userId = current_user_id();
$templateId = (int)($_POST['template_id'] ?? 0);
$cells = $_POST['cell'] ?? [];

if ($templateId <= 0 || !is_array($cells)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверные данные формы.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mRes = pg_query_params($conn, "SELECT municipality_id FROM cit_schema.users WHERE user_id = $1 LIMIT 1", [$userId]);
if (!$mRes || pg_num_rows($mRes) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не найдено муниципальное образование пользователя.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$municipalityId = (int)pg_fetch_result($mRes, 0, 'municipality_id');

$service = new TemplateService($conn);
$template = $service->getTemplateById($templateId);
$headers = $template->getHeaders();
$structure = $template->getStructure();

$columnTypes = [];
foreach ($headers as $h) {
    $name = (string)($h['name'] ?? '');
    if ($name === '') continue;
    $columnTypes[$name] = (($h['type'] ?? 'text') === 'number') ? 'number' : 'text';
}

$rowTypes = [];
foreach ($structure['rows'] ?? [] as $idx => $rowDef) {
    $rowTypes[(int)$idx] = (is_array($rowDef) && ($rowDef['rowType'] ?? 'normal') === 'comment') ? 'comment' : 'normal';
}

$ok = true;
foreach ($cells as $rIndex => &$row) {
    if (!is_array($row)) continue;
    $rowType = $rowTypes[(int)$rIndex] ?? 'normal';
    foreach ($row as $colName => &$value) {
        $value = trim((string)$value);
        if ($rowType === 'comment') continue;
        $type = $columnTypes[(string)$colName] ?? 'text';
        if ($type === 'text') continue;
        if ($value === '') { $ok = false; continue; }
        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) { $ok = false; continue; }
        $value = $normalized;
    }
    unset($value);
}
unset($row);

if (!$ok) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не все поля заполнены или заполнены некорректно.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service->saveFilledData($userId, $templateId, $municipalityId, $cells);
} catch (DomainException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Данные успешно сохранены.'], JSON_UNESCAPED_UNICODE);
