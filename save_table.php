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
      FROM users
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

/**
 * Проверка числовых полей:
 * - текстовые колонки: Показатели, Единица измерения, Комментарий — не трогаем
 * - остальные ячейки проверяем на заполненность
 */

$hasErrors = false;
foreach ($cells as $rIndex => &$row) {
    foreach ($row as $colName => &$value) {
        $value = trim((string)$value);

        // Пропускаем текстовые столбцы
        if (
            $colName === 'Показатели' ||
            $colName === 'Единица измерения' ||
            $colName === 'Комментарий'
        ) {
            continue;
        }

        // Обязательное поле: если пусто — ошибка
        if ($value === '') {
            $hasErrors = true;
            continue;
        }

        // Заменяем запятую на точку и проверяем, число ли это, то есть перевод с русского формата на формат, который понимает PHP
        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            $hasErrors = true;
            continue;
        }

        // Сохраняем уже нормализованное значение
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
$service = new TemplateService($conn);

try {
    $service->saveFilledData($userId, $templateId, $municipalityId, $cells);
    // Ответ пользователю
    echo "Данные успешно сохранены.";
    echo '<br><a href="get_table.php">Вернуться к таблице</a>';
} catch (Throwable $e) {
    http_response_code(500);
    echo "Ошибка сохранения данных: " . htmlspecialchars($e->getMessage());
}