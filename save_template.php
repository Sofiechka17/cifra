<?php

/**
 * Контроллер сохранения шаблона (JSON payload).
 * Только admin, CSRF из заголовка X-CSRF-Token.
 */
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    JsonResponse::error(405, 'Метод не поддерживается.');
    exit;
}

(new SessionGuard())->requireAdmin();
Csrf::verifyOrFail();

/**
 * Валидатор payload конструктора шаблона.
 */
final class TemplatePayloadValidator
{
    /** @return array{ok:bool,error?:string,name?:string,makeActive?:bool,headers?:array,structure?:array} */
    public function validate(array $data): array
    {
        $name = trim((string)($data['template_name'] ?? ''));
        $makeActive = !empty($data['make_active']);
        $headers = $data['headers'] ?? [];
        $structure = $data['structure'] ?? null;

        if ($name === '') return ['ok' => false, 'error' => 'Название шаблона не может быть пустым.'];
        if (!is_array($headers) || count($headers) === 0) return ['ok' => false, 'error' => 'Должен быть хотя бы один столбец.'];
        if (!is_array($structure) || !isset($structure['rows']) || !is_array($structure['rows'])) {
            return ['ok' => false, 'error' => 'Некорректная структура таблицы.'];
        }

        foreach ($headers as &$h) {
            if (!is_array($h)) return ['ok' => false, 'error' => 'Некорректный заголовок столбца.'];
            $h['name'] = trim((string)($h['name'] ?? ''));
            if ($h['name'] === '') return ['ok' => false, 'error' => 'Имя столбца не может быть пустым.'];
            $h['type'] = (($h['type'] ?? 'text') === 'number') ? 'number' : 'text';
            $h['readonly'] = !empty($h['readonly']);
        }
        unset($h);

        foreach ($structure['rows'] as $row) {
            if (!is_array($row)) return ['ok' => false, 'error' => 'Некорректная строка таблицы.'];
            $rowType = (string)($row['rowType'] ?? 'normal');
            if (!in_array($rowType, ['normal', 'comment'], true)) {
                return ['ok' => false, 'error' => 'Некорректный тип строки.'];
            }
            if (!is_array($row['cells'] ?? [])) return ['ok' => false, 'error' => 'Некорректные ячейки строки.'];
        }

        $merges = $structure['merges'] ?? [];
        if (!is_array($merges)) return ['ok' => false, 'error' => 'Некорректная структура объединений.'];

        foreach ($merges as $merge) {
            if (!is_array($merge) || !isset($merge['startRow'], $merge['startCol'])) {
                return ['ok' => false, 'error' => 'Некорректное объединение ячеек.'];
            }
            $sr = (int)$merge['startRow'];
            $sc = (int)$merge['startCol'];
            $rs = isset($merge['rowSpan']) ? (int)$merge['rowSpan'] : 1;
            $cs = isset($merge['colSpan']) ? (int)$merge['colSpan'] : 1;
            if ($sr < 0 || $sc < 0 || $rs < 1 || $cs < 1) {
                return ['ok' => false, 'error' => 'Некорректные координаты объединения.'];
            }
        }

        return ['ok' => true, 'name' => $name, 'makeActive' => $makeActive, 'headers' => $headers, 'structure' => $structure];
    }
}

$raw = (string)file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    JsonResponse::error(400, 'Некорректный формат данных (ожидается JSON).');
    exit;
}

$validator = new TemplatePayloadValidator();
$check = $validator->validate($data);
if (empty($check['ok'])) {
    JsonResponse::error(400, (string)($check['error'] ?? 'Ошибка валидации.'));
    exit;
}

$service = new TemplateService($conn);
$newId = $service->createTemplate(
    (string)$check['name'],
    (array)$check['headers'],
    (array)$check['structure'],
    (bool)$check['makeActive']
);

$msg = 'Шаблон успешно сохранён (ID=' . $newId . ')' . (!empty($check['makeActive']) ? ' и сделан активным.' : '');
JsonResponse::success($msg, ['template_id' => $newId]);
