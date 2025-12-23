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
require_auth();
require_once __DIR__ . '/core/TemplateService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Метод не поддерживается';
    exit;
}

// Текущий пользователь
$userId = current_user_id();
if ($userId === null) {
    http_response_code(401);
    echo 'Необходима авторизация';
    exit;
}

// Достаём данные из формы
$templateId = isset($_POST['template_id']) ? (int)$_POST['template_id'] : 0;
$cells      = $_POST['cell'] ?? [];

if ($templateId <= 0 || !is_array($cells)) {
    http_response_code(400);
    echo 'Неверные данные формы';
    exit;
}

// Получаем municipality_id пользователя
$sqlUser = "
    SELECT municipality_id
      FROM cit_schema.users
     WHERE user_id = $1
     LIMIT 1
";
$resUser = pg_query_params($conn, $sqlUser, [$userId]);
if (!$resUser || pg_num_rows($resUser) === 0) {
    http_response_code(400);
    echo 'Не найдено муниципальное образование пользователя';
    exit;
}
$rowUser        = pg_fetch_assoc($resUser);
$municipalityId = (int)$rowUser['municipality_id'];

// Получаем шаблон и типы колонок
$service  = new TemplateService($conn);
$template = $service->getTemplateById($templateId);
$headers  = $template->getHeaders();

// имя столбца - тип
$columnTypes = [];
foreach ($headers as $h) {
    $name = $h['name'] ?? '';
    if ($name === '') continue;
    $columnTypes[$name] = $h['type'] ?? 'text';
}

// индекс строки - rowType (normal/comment)
$structure = $template->getStructure();
$rowDefs   = $structure['rows'] ?? [];
$rowTypes  = [];
foreach ($rowDefs as $idx => $rowDef) {
    if (is_array($rowDef) && array_key_exists('rowType', $rowDef)) {
        $rowTypes[$idx] = $rowDef['rowType'] ?? 'normal';
    } else {
        $rowTypes[$idx] = 'normal';
    }
}

/**
 * Проверка числовых полей:
 * - текстовые колонки: Показатели, Единица измерения, Комментарий — не трогаем
 * - остальные ячейки проверяем на заполненность
 */

$hasErrors = false;

foreach ($cells as $rIndex => &$row) {
    $rowType = $rowTypes[$rIndex] ?? 'normal';

    foreach ($row as $colName => &$value) {
        $value = trim((string)$value);

        if ($rowType === 'comment') {
            // строка комментария — любые текстовые данные
            continue;
        }

        $type = $columnTypes[$colName] ?? 'text';

        // текстовые колонки не проверяем как числа
        if ($type === 'text') {
            continue;
        }

        // числовое поле обязательно
        if ($value === '') {
            $hasErrors = true;
            continue;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            $hasErrors = true;
            continue;
        }

        // сохраняем нормализованное значение
        $value = $normalized;
    }
}
unset($row, $value);

// Если есть ошибки — ничего не сохраняем, общее сообщение
if ($hasErrors) {
    http_response_code(400);
    echo '<h3>Не все поля заполнены или заполнены некорректно.</h3>';
    echo '<p>Заполните все обязательные числовые ячейки, затем повторите отправку.</p>';
    echo '<p><a href="javascript:history.back()">Вернуться к заполнению таблицы</a></p>';
    exit;
}

// Сохраняем данные через сервис (Фасад)
try {
    $service->saveFilledData($userId, $templateId, $municipalityId, $cells);
    // Ответ пользователю
    echo "Данные успешно сохранены.";
    echo '<br><a href="get_table.php">Вернуться к таблице</a>';
} catch (Throwable $e) {
    http_response_code(500);
    echo "Ошибка сохранения данных: " . htmlspecialchars($e->getMessage());
}

