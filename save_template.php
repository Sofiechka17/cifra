<?php
/**
 * Обработчик сохранения шаблона из конструктора (AJAX).
 * Принимает JSON
 */

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/core/TemplateService.php';

header('Content-Type: application/json; charset=utf-8');

// Только админ
try {
    require_admin();
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Доступ запрещён'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Читаем сырое тело запроса
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    echo json_encode([
        'success' => false,
        'message' => 'Некорректный формат данных (ожидается JSON).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$name       = trim($data['template_name'] ?? '');
$makeActive = !empty($data['make_active']);
$headers    = $data['headers'] ?? [];
$structure  = $data['structure'] ?? null;

// валидация
if ($name === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Название шаблона не может быть пустым.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_array($headers) || count($headers) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Должен быть хотя бы один столбец.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_array($structure) || !isset($structure['rows']) || !is_array($structure['rows'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Некорректная структура таблицы.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Проверяем заголовки
foreach ($headers as &$h) {
    $h['name'] = trim($h['name'] ?? '');
    if ($h['name'] === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Имя столбца не может быть пустым.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $h['type'] = ($h['type'] ?? 'text') === 'number' ? 'number' : 'text';
    $h['readonly'] = !empty($h['readonly']);
}
unset($h);

// проверка строк
foreach ($structure['rows'] as $row) {
    if (!is_array($row)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка в шаблоне: одна из строк таблицы записана неправильно.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $rowType = $row['rowType'] ?? 'normal';
    $cells   = $row['cells'] ?? [];

    if (!in_array($rowType, ['normal', 'comment'], true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Некорректный тип строки.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!is_array($cells)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка в шаблоне: внутри строки таблицы неправильно переданы ячейки.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// проверка объединений
$merges = $structure['merges'] ?? [];
if (!is_array($merges)) {
    echo json_encode([
        'success' => false,
        'message' => 'Некорректная структура объединений ячеек.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

foreach ($merges as $merge) {
    if (!is_array($merge)) {
        echo json_encode([
            'success' => false,
            'message' => 'Ошибка в объединениях: одно из объединений задано неверно.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($merge['startRow'], $merge['startCol'])) {
        echo json_encode([
            'success' => false,
            'message' => 'У объединения ячеек должны быть заданы startRow и startCol.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sr = (int)$merge['startRow'];
    $sc = (int)$merge['startCol'];
    $rs = isset($merge['rowSpan']) ? (int)$merge['rowSpan'] : 1;
    $cs = isset($merge['colSpan']) ? (int)$merge['colSpan'] : 1;

    if ($sr < 0 || $sc < 0 || $rs < 1 || $cs < 1) {
        echo json_encode([
            'success' => false,
            'message' => 'У объединения ячеек некорректные координаты или размер.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $service = new TemplateService($conn);
    $newId = $service->createTemplate($name, $headers, $structure, $makeActive);

    echo json_encode([
        'success' => true,
        'message' => 'Шаблон успешно сохранён (ID=' . $newId . ')' . ($makeActive ? ' и сделан активным.' : '')
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка сохранения шаблона: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
