<?php
/**
 * Контроллер сохранения шаблона (JSON payload).
 * Только admin, CSRF из заголовка X-CSRF-Token.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не поддерживается.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_admin();
Csrf::verifyOrFail();

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректный формат данных (ожидается JSON).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = trim((string)($data['template_name'] ?? ''));
$makeActive = !empty($data['make_active']);
$headers = $data['headers'] ?? [];
$structure = $data['structure'] ?? null;

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Название шаблона не может быть пустым.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!is_array($headers) || count($headers) === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Должен быть хотя бы один столбец.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!is_array($structure) || !isset($structure['rows']) || !is_array($structure['rows'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректная структура таблицы.'], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach ($headers as &$h) {
    if (!is_array($h)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Некорректный заголовок столбца.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $h['name'] = trim((string)($h['name'] ?? ''));
    if ($h['name'] === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Имя столбца не может быть пустым.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $h['type'] = (($h['type'] ?? 'text') === 'number') ? 'number' : 'text';
    $h['readonly'] = !empty($h['readonly']);
}
unset($h);

$service = new TemplateService($conn);
$newId = $service->createTemplate($name, $headers, $structure, $makeActive);

$msg = 'Шаблон успешно сохранён (ID=' . $newId . ')' . ($makeActive ? ' и сделан активным.' : '');
echo json_encode(['success' => true, 'message' => $msg, 'template_id' => $newId], JSON_UNESCAPED_UNICODE);
