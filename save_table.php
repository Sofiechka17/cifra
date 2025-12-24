<?php
/**
 * Обработка отправки заполненной таблицы
 * Принимает POST с полями:
 *  - template_id
 *  - cell[номер_строки][название_столбца]
 * Проверяет данные и сохраняет их в filled_data в формате JSON.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/core/TemplateService.php';

require_auth();

/**
 * Формат ответа (JSON или HTML) в зависимости от запроса.
 */
final class ResponseMode
{
    /**
     * Определяет, является ли запрос AJAX/JSON.
     *
     * @param array<string, mixed> $server $_SERVER
     * @return bool
     */
    public static function isAjax(array $server): bool
    {
        $isXhr = !empty($server['HTTP_X_REQUESTED_WITH']) && strtolower((string)$server['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        $wantsJson = !empty($server['HTTP_ACCEPT']) && strpos((string)$server['HTTP_ACCEPT'], 'application/json') !== false;
        return $isXhr || $wantsJson;
    }
}

/**
 * Класс, который принимает заполненную пользователем таблицу и сохраняет её в базу.
 */
final class SaveTableHandler
{
    /** @var resource|\PgSql\Connection */
    private $conn;

    /** @var TemplateService */
    private TemplateService $service;

    /** @var bool */
    private bool $isAjax;

    /**
     * @param resource|\PgSql\Connection $conn Соединение PostgreSQL.
     * @param TemplateService $service Сервис шаблонов.
     * @param bool $isAjax Режим ответа JSON/HTML.
     */
    public function __construct($conn, TemplateService $service, bool $isAjax)
    {
        $this->conn = $conn;
        $this->service = $service;
        $this->isAjax = $isAjax;
    }

    /**
     * Точка входа.
     *
     * @param array<string, mixed> $server $_SERVER
     * @param array<string, mixed> $post $_POST
     * @return void
     */
    public function handle(array $server, array $post): void
    {
        if (($server['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->respondError(405, 'Метод не поддерживается');
            return;
        }

        $userId = current_user_id();
        if ($userId === null) {
            $this->respondError(401, 'Необходима авторизация');
            return;
        }

        $templateId = isset($post['template_id']) ? (int)$post['template_id'] : 0;
        $cells = $post['cell'] ?? [];

        if ($templateId <= 0 || !is_array($cells)) {
            $this->respondError(400, 'Неверные данные формы');
            return;
        }

        $municipalityId = $this->loadMunicipalityIdByUserId($userId);
        if ($municipalityId === null) {
            $this->respondError(400, 'Не найдено муниципальное образование пользователя');
            return;
        }

        try {
            $template = $this->service->getTemplateById($templateId);
            $headers = $template->getHeaders();
            $structure = $template->getStructure();
        } catch (Throwable $e) {
            $this->respondError(500, 'Ошибка чтения шаблона: ' . $e->getMessage());
            return;
        }

        $columnTypes = $this->buildColumnTypes($headers);
        $rowTypes = $this->buildRowTypes($structure['rows'] ?? []);

        $normalizedCells = $cells;
        $hasErrors = !$this->validateAndNormalizeCells($normalizedCells, $columnTypes, $rowTypes);

        if ($hasErrors) {
            $this->respondValidationError();
            return;
        }

        try {
            $this->service->saveFilledData($userId, $templateId, $municipalityId, $normalizedCells);
            $this->respondSuccess('Данные успешно сохранены.');
        } catch (Throwable $e) {
            $this->respondError(500, 'Ошибка сохранения данных: ' . $e->getMessage());
        }
    }

    /**
     * Загружает municipality_id пользователя.
     *
     * @param int $userId ID пользователя.
     * @return int|null ID МО или null.
     */
    private function loadMunicipalityIdByUserId(int $userId): ?int
    {
        $sql = "SELECT municipality_id FROM cit_schema.users WHERE user_id = $1 LIMIT 1";
        $res = pg_query_params($this->conn, $sql, [$userId]);

        if (!$res || pg_num_rows($res) === 0) {
            return null;
        }

        $row = pg_fetch_assoc($res);
        return isset($row['municipality_id']) ? (int)$row['municipality_id'] : null;
    }

    /**
     * Строит карту "имя столбца -> тип (text/number)".
     *
     * @param array<int, array<string, mixed>> $headers Заголовки.
     * @return array<string, string>
     */
    private function buildColumnTypes(array $headers): array
    {
        $types = [];
        foreach ($headers as $h) {
            $name = (string)($h['name'] ?? '');
            if ($name === '') continue;
            $types[$name] = (($h['type'] ?? 'text') === 'number') ? 'number' : 'text';
        }
        return $types;
    }

    /**
     * Строит карту "индекс строки -> rowType (normal/comment)".
     *
     * @param array<int, mixed> $rowDefs Структура строк.
     * @return array<int, string>
     */
    private function buildRowTypes(array $rowDefs): array
    {
        $rowTypes = [];
        foreach ($rowDefs as $idx => $rowDef) {
            if (is_array($rowDef) && array_key_exists('rowType', $rowDef)) {
                $rowTypes[(int)$idx] = (string)($rowDef['rowType'] ?? 'normal');
            } else {
                $rowTypes[(int)$idx] = 'normal';
            }
        }
        return $rowTypes;
    }

    /**
     * Валидирует и нормализует значения ячеек.
     *
     * Правила:
     * - если rowType = comment: любые значения допускаются;
     * - если тип столбца text: не проверяем как число;
     * - если тип столбца number: поле обязательно и должно быть числом (нормализуем запятую в точку).
     *
     * @param array<string, mixed> $cells Данные ячеек (будут модифицированы).
     * @param array<string, string> $columnTypes Типы колонок.
     * @param array<int, string> $rowTypes Типы строк.
     * @return bool true если всё ок.
     */
    private function validateAndNormalizeCells(array &$cells, array $columnTypes, array $rowTypes): bool
    {
        $ok = true;

        foreach ($cells as $rIndex => &$row) {
            if (!is_array($row)) continue;

            $rowType = $rowTypes[(int)$rIndex] ?? 'normal';

            foreach ($row as $colName => &$value) {
                $value = trim((string)$value);

                if ($rowType === 'comment') {
                    continue;
                }

                $type = $columnTypes[(string)$colName] ?? 'text';

                if ($type === 'text') {
                    continue;
                }

                if ($value === '') {
                    $ok = false;
                    continue;
                }

                $normalized = str_replace(',', '.', $value);
                if (!is_numeric($normalized)) {
                    $ok = false;
                    continue;
                }

                $value = $normalized;
            }
        }

        unset($row, $value);
        return $ok;
    }

    /** Ответ “ошибка валидации”. */
    private function respondValidationError(): void
    {
        if ($this->isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Не все поля заполнены или заполнены некорректно.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(400);
        echo '<h3>Не все поля заполнены или заполнены некорректно.</h3>';
        echo '<p>Заполните все обязательные числовые ячейки, затем повторите отправку.</p>';
        echo '<p><a href="javascript:history.back()">Вернуться к заполнению таблицы</a></p>';
        exit;
    }

    /**
     * Отдаёт успешный ответ.
     *
     * @param string $message Сообщение.
     */
    private function respondSuccess(string $message): void
    {
        if ($this->isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo htmlspecialchars($message);
        echo '<br><a href="get_table.php">Вернуться к таблице</a>';
        exit;
    }

    /**
     * Отдаёт ответ с ошибкой.
     *
     * @param int $statusCode HTTP-код.
     * @param string $message Сообщение.
     */
    private function respondError(int $statusCode, string $message): void
    {
        http_response_code($statusCode);

        if ($this->isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo htmlspecialchars($message);
        exit;
    }
}

$isAjax = ResponseMode::isAjax($_SERVER);
$handler = new SaveTableHandler($conn, new TemplateService($conn), $isAjax);
$handler->handle($_SERVER, $_POST);
