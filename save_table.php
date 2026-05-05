<?php

/**
 * Контроллер сохранения заполненной таблицы.
 */
require_once __DIR__ . '/bootstrap.php';

$guard = new SessionGuard();
$guard->requireAuth();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    JsonResponse::error(405, 'Метод не поддерживается.');
    exit;
}

Csrf::verifyOrFail();

$userId = $guard->userId();
if ($userId === null) {
    JsonResponse::error(401, 'Необходима авторизация.');
    exit;
}

$templateId = (int)($_POST['template_id'] ?? 0);
$cells = $_POST['cell'] ?? [];

if ($templateId <= 0 || !is_array($cells)) {
    JsonResponse::error(400, 'Неверные данные формы.');
    exit;
}

$userRepo = new UserRepository($conn);
$municipalityId = $userRepo->findMunicipalityIdByUserId($userId);
if ($municipalityId === null) {
    JsonResponse::error(400, 'Не найдено муниципальное образование пользователя.');
    exit;
}

$service = new TemplateService($conn);
$template = $service->getTemplateById($templateId);
$headers = $template->getHeaders();
$structure = $template->getStructure();

/** @var array<string,string> $columnTypes */
$columnTypes = [];
foreach ($headers as $h) {
    $name = (string)($h['name'] ?? '');
    if ($name === '') continue;
    $columnTypes[$name] = (($h['type'] ?? 'text') === 'number') ? 'number' : 'text';
}

/** @var array<int,string> $rowTypes */
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
    JsonResponse::error(400, 'Не все поля заполнены или заполнены некорректно.');
    exit;
}

try {
    $service->saveFilledData($userId, $templateId, $municipalityId, $cells);
} catch (DomainException $e) {
    JsonResponse::error(400, $e->getMessage());
    exit;
}

JsonResponse::success('Данные успешно сохранены.');
