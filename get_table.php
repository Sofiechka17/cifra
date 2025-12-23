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
$rowDefs      = $structure['rows'] ?? [];
$columnsCount = count($headers);
/**
 * объединения для HTML
 */
$mergeTopLeft = [];
$skipCells    = [];

if (is_array($merges)) {
    foreach ($merges as $merge) {
        if (!is_array($merge)) {
            continue;
        }

        $sr = isset($merge['startRow']) ? (int)$merge['startRow'] : 0;
        $sc = isset($merge['startCol']) ? (int)$merge['startCol'] : 0;
        $rs = isset($merge['rowSpan'])  ? (int)$merge['rowSpan']  : 1;
        $cs = isset($merge['colSpan'])  ? (int)$merge['colSpan']  : 1;

        if ($sr < 0 || $sc < 0 || $rs < 1 || $cs < 1) {
            continue;
        }

        // не выходим за границы таблицы
        if ($sr >= count($rowDefs) || $sc >= $columnsCount) {
            continue;
        }

        $mergeTopLeft[$sr][$sc] = [
            'rowSpan' => $rs,
            'colSpan' => $cs,
        ];

        for ($r = $sr; $r < $sr + $rs; $r++) {
            for ($c = $sc; $c < $sc + $cs; $c++) {
                if ($r == $sr && $c == $sc) {
                    continue; 
                }
                $skipCells[$r][$c] = true;
            }
        }
    }
}
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
    <nav class="centered-nav">
        <div class="nav-links">
            <a href="index.php">Главная</a>
            <a href="get_table.php">Заполнить форму</a>
        </div>
        <div class="user-municipality">
            <?= htmlspecialchars($municipalityName) ?>
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

                <div id="data-table-container">
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
                    <?php foreach ($rowDefs as $rIndex => $rowDef): ?>
                            <?php
                            // Распаковываем структуру строки:
                            // либо новая форма {rowType, cells}, либо старая — просто массив значений
                            if (is_array($rowDef)
                                && array_key_exists('rowType', $rowDef)
                                && array_key_exists('cells', $rowDef)
                                && is_array($rowDef['cells'])
                            ) {
                                $rowType = $rowDef['rowType'] ?? 'normal';
                                $cells   = $rowDef['cells'];
                            } else {
                                $rowType = 'normal';
                                $cells   = is_array($rowDef) ? $rowDef : [];
                            }

                            if ($rowType === 'comment'): ?>
                                <!-- Строка комментария: одна большая ячейка -->
                                <?php
                                // Значение по умолчанию из ячейки "Комментарий", если такая есть
                                $commentValue = $cells['Комментарий'] ?? '';
                                ?>
                                <tr class="comment-row">
                                    <td colspan="<?= (int)$columnsCount ?>">
                                        <textarea
                                            name="cell[<?= (int)$rIndex ?>][Комментарий]"
                                        ><?= htmlspecialchars($commentValue) ?></textarea>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <!-- Обычная строка (с учётом merges) -->
                                <tr>
                                    <?php for ($cIndex = 0; $cIndex < $columnsCount; $cIndex++): ?>
                                        <?php
                                        // Если эта ячейка “поглощена” объединением – не рисуем её
                                        if (!empty($skipCells[$rIndex][$cIndex])) {
                                            continue;
                                        }

                                        $h       = $headers[$cIndex];
                                        $name    = $h['name'];
                                        $type    = $h['type'] ?? 'text';
                                        $readonly = !empty($h['readonly']);
                                        $value   = $cells[$name] ?? '';

                                        $rowspan = 1;
                                        $colspan = 1;
                                        if (!empty($mergeTopLeft[$rIndex][$cIndex])) {
                                            $rowspan = (int)$mergeTopLeft[$rIndex][$cIndex]['rowSpan'];
                                            $colspan = (int)$mergeTopLeft[$rIndex][$cIndex]['colSpan'];
                                        }

                                        $attrs = '';
                                        if ($rowspan > 1) {
                                            $attrs .= ' rowspan="' . $rowspan . '"';
                                        }
                                        if ($colspan > 1) {
                                            $attrs .= ' colspan="' . $colspan . '"';
                                        }
                                        ?>
                                        <td<?= $attrs ?>>
                                            <input
                                                class="table-input"
                                                <?= $readonly ? 'readonly' : '' ?>
                                                type="<?= $type === 'number' ? 'number' : 'text' ?>"
                                                name="cell[<?= (int)$rIndex ?>][<?= htmlspecialchars($name, ENT_QUOTES) ?>]"
                                                value="<?= htmlspecialchars($value) ?>"
                                            >
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" id="saveTableBtn">Сохранить и отправить</button>
            </form>
        <?php endif; ?>
    </section>
</main>
</body>
</html>