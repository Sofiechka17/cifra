<?php
/**
 * Страница администратора
 * Отображает:
 * - список заполненных пользователями таблиц с возможностью выгрузки в Excel
 * - список заявок обратной связи с возможностью выгрузки в Excel
 * - конструктор шаблона таблицы (для пользователей)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/core/TemplateService.php';

require_admin(); 
$service = new TemplateService($conn);

/**
 * Получение списка всех заполненных таблиц 
 */
$filledResult = pg_query($conn, "
    SELECT f.filled_data_id, u.user_full_name, m.municipality_name, f.filled_date 
    FROM cit_schema.filled_data f
    JOIN cit_schema.users u ON f.user_id = u.user_id
    JOIN cit_schema.municipalities m ON f.municipality_id = m.municipality_id
    ORDER BY f.filled_date DESC
");

/**
 * Получение всех заявок обратной связи
 */
$feedbackResult = pg_query($conn, "
    SELECT fr.feedback_id, 
           fr.full_name_feedback, 
           fr.phone_feedback, 
           fr.problem_description_feedback
    FROM cit_schema.feedback_requests fr
    ORDER BY fr.feedback_id DESC
");

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Администратор - отчеты и шаблоны</title>
    <link rel="stylesheet" href="styles.css">
    <script src="constructor.js" defer></script>
</head>
<body class="admin-body">
    <h2>Заполненные таблицы</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>МО</th>
            <th>Дата</th>
            <th>Действие</th>
        </tr>
        <?php while ($row = pg_fetch_assoc($filledResult)): ?>
            <tr>
                <td><?= $row["filled_data_id"] ?></td>
                <td><?= htmlspecialchars($row["user_full_name"]) ?></td>
                <td><?= htmlspecialchars($row["municipality_name"]) ?></td>
                <td><?= $row["filled_date"] ?></td>
                <td>
                    <form action="export_excel.php" method="get" style="margin:0;">
                        <input type="hidden" name="filled_id" value="<?= $row["filled_data_id"] ?>">
                        <button type="submit" class="btn">Выгрузить в Excel</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Список обратной связи</h2>
<table>
    <tr>
        <th>ID</th>
        <th>ФИО</th>
        <th>Телефон</th>
        <th>Текст обращения</th>
    </tr>
    <?php while ($fb = pg_fetch_assoc($feedbackResult)): ?>
        <tr>
            <td><?= $fb["feedback_id"] ?></td>
            <td><?= htmlspecialchars($fb["full_name_feedback"]) ?></td>
            <td><?= htmlspecialchars($fb["phone_feedback"]) ?></td>
            <td><?= nl2br(htmlspecialchars($fb["problem_description_feedback"])) ?></td>
        </tr>
    <?php endwhile; ?>
</table>

<form action="export_feedback_excel.php" method="get" style="margin-top:15px;">
    <button type="submit" class="btn">Выгрузить все заявки в Excel</button>
</form>

<hr style="margin:30px 0;">

    <h2>Конструктор шаблона таблицы</h2>

    <p class="constructor-intro">
        Здесь вы можете создать или изменить шаблон отчётной таблицы.
        Активный шаблон увидят все пользователи на странице «Заполнить форму».
    </p>

    <p class="merge-instructions">
        Чтобы объединить ячейки: зажмите <b>Shift</b> и кликните сначала по первой ячейке диапазона,
        потом по последней. Все ячейки внутри прямоугольника выделятся — после этого нажмите
        кнопку «Объединить выбранные ячейки».<br>
        Чтобы разъединить: таким же образом выделите диапазон и нажмите
        «Разъединить ячейки».<br>
        Кнопки «Удалить выбранные строки» и «Удалить выбранные столбцы» удаляют
        строки/столбцы, попадающие в выделенный диапазон.
    </p>

    <form id="template-form" onsubmit="return false;">

        <label for="template-name">Название шаблона:</label>
        <input type="text" id="template-name" name="template_name" required>

        <div class="template-active-wrapper">
            <label class="template-active-label">
                <input type="checkbox" id="make-active-checkbox">
                Сделать этот шаблон активным
            </label>
        </div>

        <div class="rows-cols-wrapper">
            <label>
                Количество строк:
                <input type="number" id="rows-count" min="1" max="200" value="5">
            </label>
            <label>
                Количество столбцов:
                <input type="number" id="cols-count" min="1" max="20" value="7">
            </label>
        </div>

        <div class="constructor-toolbar">
            <button type="button" id="generate-table-btn">Сгенерировать таблицу</button>
            <button type="button" id="clear-table-btn">Очистить содержимое</button>
            <button type="button" id="reset-table-btn">Сбросить к дефолтному виду</button>
        </div>

        <div class="merge-toolbar">
            <p>Блок управления ячейками:</p>
            <button type="button" id="merge-cells-btn">Объединить выбранные ячейки</button>
            <button type="button" id="unmerge-cells-btn">Разъединить ячейки</button>
            <button type="button" id="delete-row-btn">Удалить выбранные строки</button>
            <button type="button" id="delete-col-btn">Удалить выбранные столбцы</button>
            <span id="selection-info">Выделено ячеек: 0</span>
        </div>
        
        <div id="constructor-messages" class="constructor-messages"></div>

        <div id="constructor-wrapper">
            <table id="constructor-table">
                <!-- JS строит таблицу -->
            </table>
        </div>

        <div class="constructor-actions">
            <button type="button" id="save-template-btn">Сохранить шаблон</button>
        </div>
    </form>
</body>
</html>