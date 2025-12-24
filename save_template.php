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

/**
 * Валидатор входного JSON шаблона.
 */
final class TemplatePayloadValidator
{
    /**
     * Проверяет данные, которые пришли из конструктора шаблона.
     *
     * @param array<string, mixed> $data Payload.
     * @return array{ok:bool, error?:string, name?:string, makeActive?:bool, headers?:array, structure?:array}
     */
    public function validate(array $data): array
    {
        $name = trim((string)($data['template_name'] ?? ''));
        $makeActive = !empty($data['make_active']);
        $headers = $data['headers'] ?? [];
        $structure = $data['structure'] ?? null;

        if ($name === '') {
            return ['ok' => false, 'error' => 'Название шаблона не может быть пустым.'];
        }
        if (!is_array($headers) || count($headers) === 0) {
            return ['ok' => false, 'error' => 'Должен быть хотя бы один столбец.'];
        }
        if (!is_array($structure) || !isset($structure['rows']) || !is_array($structure['rows'])) {
            return ['ok' => false, 'error' => 'Некорректная структура таблицы.'];
        }

        // Заголовки
        foreach ($headers as &$h) {
            if (!is_array($h)) {
                return ['ok' => false, 'error' => 'Некорректный заголовок столбца.'];
            }
            $h['name'] = trim((string)($h['name'] ?? ''));
            if ($h['name'] === '') {
                return ['ok' => false, 'error' => 'Имя столбца не может быть пустым.'];
            }
            $h['type'] = (($h['type'] ?? 'text') === 'number') ? 'number' : 'text';
            $h['readonly'] = !empty($h['readonly']);
        }
        unset($h);

        // Строки
        foreach ($structure['rows'] as $row) {
            if (!is_array($row)) {
                return ['ok' => false, 'error' => 'Ошибка в шаблоне: одна из строк таблицы записана неправильно.'];
            }
            $rowType = (string)($row['rowType'] ?? 'normal');
            $cells = $row['cells'] ?? [];

            if (!in_array($rowType, ['normal', 'comment'], true)) {
                return ['ok' => false, 'error' => 'Некорректный тип строки.'];
            }
            if (!is_array($cells)) {
                return ['ok' => false, 'error' => 'Ошибка в шаблоне: внутри строки таблицы неправильно переданы ячейки.'];
            }
        }

        // Объединённые ячейки (merges)
        $merges = $structure['merges'] ?? [];
        if (!is_array($merges)) {
            return ['ok' => false, 'error' => 'Некорректная структура объединений ячеек.'];
        }

        foreach ($merges as $merge) {
            if (!is_array($merge)) {
                return ['ok' => false, 'error' => 'Ошибка в объединениях: одно из объединений задано неверно.'];
            }
            if (!isset($merge['startRow'], $merge['startCol'])) {
                return ['ok' => false, 'error' => 'У объединения ячеек должны быть заданы startRow и startCol.'];
            }

            $sr = (int)$merge['startRow'];
            $sc = (int)$merge['startCol'];
            $rs = isset($merge['rowSpan']) ? (int)$merge['rowSpan'] : 1;
            $cs = isset($merge['colSpan']) ? (int)$merge['colSpan'] : 1;

            if ($sr < 0 || $sc < 0 || $rs < 1 || $cs < 1) {
                return ['ok' => false, 'error' => 'У объединения ячеек некорректные координаты или размер.'];
            }
        }

        return [
            'ok' => true,
            'name' => $name,
            'makeActive' => $makeActive,
            'headers' => $headers,
            'structure' => $structure,
        ];
    }
}

/**
 * Класс, который "принимает запрос" на сохранение шаблона и делает всю работу:
 *
 * - Проверяет, что пользователь — админ.
 * - Читает JSON из запроса (то, что прислал конструктор).
 * - Проверяет данные через валидатор (TemplatePayloadValidator).
 * - Если всё нормально — сохраняет шаблон в базе через TemplateService.
 * - Возвращает ответ JSON: success=true/false и текст сообщения.
 */
final class SaveTemplateHandler
{
    /** @var TemplateService */
    private TemplateService $service;

    /** @var TemplatePayloadValidator */
    private TemplatePayloadValidator $validator;

    /**
     * @param TemplateService $service Сервис шаблонов.
     * @param TemplatePayloadValidator $validator Валидатор payload.
     */
    public function __construct(TemplateService $service, TemplatePayloadValidator $validator)
    {
        $this->service = $service;
        $this->validator = $validator;
    }

    /**
     * Запускает обработку.
     *
     * @param string $rawInput Сырое тело запроса.
     * @return void
     */
    public function handle(string $rawInput): void
    {
        try {
            require_admin();
        } catch (Throwable $e) {
            $this->respond(false, 'Доступ запрещён');
            return;
        }

        $data = json_decode($rawInput, true);
        if (!is_array($data)) {
            $this->respond(false, 'Некорректный формат данных (ожидается JSON).');
            return;
        }

        $check = $this->validator->validate($data);
        if (empty($check['ok'])) {
            $this->respond(false, (string)($check['error'] ?? 'Ошибка валидации.'));
            return;
        }

        try {
            $newId = $this->service->createTemplate(
                (string)$check['name'],
                (array)$check['headers'],
                (array)$check['structure'],
                (bool)$check['makeActive']
            );

            $msg = 'Шаблон успешно сохранён (ID=' . $newId . ')' . (!empty($check['makeActive']) ? ' и сделан активным.' : '');
            $this->respond(true, $msg);
        } catch (Throwable $e) {
            $this->respond(false, 'Ошибка сохранения шаблона: ' . $e->getMessage());
        }
    }

    /**
     * Возвращает JSON-ответ.
     *
     * @param bool $success Успех.
     * @param string $message Сообщение.
     */
    private function respond(bool $success, string $message): void
    {
        echo json_encode(['success' => $success, 'message' => $message], JSON_UNESCAPED_UNICODE);
    }
}

$handler = new SaveTemplateHandler(new TemplateService($conn), new TemplatePayloadValidator());
$handler->handle((string)file_get_contents('php://input'));
