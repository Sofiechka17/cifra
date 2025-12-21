<?php
/**
 * Страница заполнения активной отчётной таблицы.
 *
 * Это НОВАЯ версия get_table.php.
 * Теперь файл не отдаёт JSON, а сразу выводит HTML-форму с таблицей:
 *  - проверяем, что пользователь авторизован;
 *  - получаем активный шаблон через TemplateService (паттерн Фасад);
 *  - строим таблицу по заголовкам и структуре шаблона;
 *  - отправляем заполненные данные в save_table.php.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Если пользователь не залогинен, отправим на login.php
require_auth();

// Подключаем сервис шаблонов (Фасад)
require_once __DIR__ . '/core/TemplateService.php';

// Создаём сервис и получаем активный шаблон из БД
$service  = new TemplateService($conn);
$template = $service->getActiveTemplate();

// Можно ли этот шаблон использовать для заполнения
// Если состояние шаблона не активен, то выводим сообщение, что его ещё не создали/не активировали
$noTemplate = !$template->canBeUsedForFill();

// Название МО берём из сессии
$municipalityName = current_municipality_name() ?? 'Муниципальное образование';

// Заголовки колонок и структура строк берем из объекта шаблона
$headers   = $template->getHeaders();
$structure = $template->getStructure();
$rows      = $structure['rows'] ?? [];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Заполнение таблицы — ИССД</title>
    <link rel="stylesheet" href="styles.css">
    <script src="script.js" defer></script>
</head>
<body>
<header>
    <div class="brand">
        <div class="logo">
            <img src="default-logo_w152_fitted.webp" alt="Логотип"
                 style="width:30%; height:100%; object-fit:contain;">
        </div>
        <span class="system-name">Информационная система сбора данных</span>
    </div>
    <nav>
        <div class="nav-links">
            <a href="index.php">Главная</a>
            <a href="get_table.php">Заполнить форму</a>
        </div>
    </nav>
</header>

<main>
    <section>
        <h2>Заполнение формы</h2>

        <?php if ($noTemplate): ?>
            <!-- Сообщение, если админ ещё не создал или не активировал шаблон -->
            <div class="message message-error">
                Активный шаблон таблицы ещё не создан администратором.
            </div>
        <?php else: ?>
            <!-- Информация о МО и названии активного шаблона -->
            <p class="main-text">
                Муниципальное образование:
                <strong><?= htmlspecialchars($municipalityName) ?></strong><br>
                Шаблон: <strong><?= htmlspecialchars($template->getName()) ?></strong>
            </p>

            <!-- Форма отправки заполненной таблицы-->
            <form id="data-form" method="post" action="save_table.php">
                <!-- Передаём ID активного шаблона скрытым полем -->
                <input type="hidden" name="template_id" value="<?= (int)$template->getId() ?>">

                <table id="data-table">
                    <thead>
                    <tr>
                        <?php foreach ($headers as $h): ?>
                            <!-- Заголовки столбцов берем из JSON template_headers -->
                            <th><?= htmlspecialchars($h['name']) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $rIndex => $row): ?>
                        <tr>
                            <?php foreach ($headers as $hIndex => $h): ?>
                                <?php
                                // Имя столбца, тип и флаг "только для чтения"
                                $name      = $h['name'];
                                $type      = $h['type'] ?? 'text';
                                $readonly  = !empty($h['readonly']);

                                // Значение ячейки из структуры (если есть)
                                $value     = $row[$name] ?? '';

                                // Имя поля в POST: cell[0]["2022"], cell[1]["2023"] и т.п.
                                $inputName = "cell[$rIndex][" . htmlspecialchars($name, ENT_QUOTES) . "]";
                                ?>
                                <td>
                                    <input
                                        class="table-input"
                                        <?= $readonly ? 'readonly' : '' ?>
                                        type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                        name="<?= $inputName ?>"
                                        value="<?= htmlspecialchars($value) ?>"
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <button type="submit">Сохранить и отправить</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
