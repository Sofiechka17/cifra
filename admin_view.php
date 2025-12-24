<?php
/**
 * Страница администратора
 * Отображает:
 * - список заполненных пользователями таблиц с возможностью выгрузки в Excel
 * - список заявок обратной связи с возможностью выгрузки в Excel
 * - конструктор шаблона таблицы (для пользователей)
 * - аналитика по заполненным таблицам (Chart.js)
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
    SELECT 
        f.filled_data_id,
        f.template_id,
        f.filled_data,
        u.user_full_name,
        m.municipality_id,
        m.municipality_name,
        t.template_name,
        f.filled_date 
    FROM cit_schema.filled_data f
    JOIN cit_schema.users u ON f.user_id = u.user_id
    JOIN cit_schema.municipalities m ON f.municipality_id = m.municipality_id
    JOIN cit_schema.table_templates t ON t.template_id = f.template_id
    ORDER BY f.filled_date DESC
");

$filledRowsForJs = [];
if ($filledResult) {
    while ($r = pg_fetch_assoc($filledResult)) {
        $filledRowsForJs[] = $r;
    }
}

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
/**
 * Загрузка конкретного шаблона по ID (если передан ?template_id=...)
 */
$loadedTemplateArray = null;

if (!empty($_GET['template_id'])) {
    $tplId = (int)$_GET['template_id'];
    if ($tplId > 0) {
        try {
            $templateObj = $service->getTemplateById($tplId); 

            if ($templateObj) {
                $loadedTemplateArray = [
                    'headers'   => $templateObj->getHeaders(),
                    'structure' => $templateObj->getStructure(),
                ];
            }
        } catch (Exception $e) {
            $loadedTemplateArray = null;
        }
    }
}

/**
 * Список всех шаблонов для выпадающего списка
 * Здесь читаем данные напрямую из таблицы шаблонов
 */
$templatesList = [];
$tplRes = pg_query($conn, "
    SELECT template_id, template_name, is_active
    FROM cit_schema.table_templates
    ORDER BY template_id DESC
");
if ($tplRes) {
    while ($row = pg_fetch_assoc($tplRes)) {
        $templatesList[] = $row;
    }
}
/**
 * Список МО для первого комбобокса диаграммы
 */
$municipalitiesList = [];
$moRes = pg_query($conn, "SELECT municipality_id, municipality_name FROM cit_schema.municipalities ORDER BY municipality_name");
if ($moRes) {
    while ($mo = pg_fetch_assoc($moRes)) {
        $municipalitiesList[] = $mo;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Администратор - отчеты и шаблоны</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Передаём в JS загруженный из БД шаблон (если есть) -->
    <script>
        window.initialTemplate = <?= $loadedTemplateArray ? json_encode($loadedTemplateArray, JSON_UNESCAPED_UNICODE) : 'null'; ?>;
        window.municipalitiesList = <?= json_encode($municipalitiesList, JSON_UNESCAPED_UNICODE) ?>;
        window.filledRowsForJs = <?= json_encode($filledRowsForJs, JSON_UNESCAPED_UNICODE) ?>;
    </script>
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

        <?php foreach ($filledRowsForJs as $row): ?>
            <tr>
                <td><?= (int)($row["filled_data_id"] ?? 0) ?></td>
                <td><?= htmlspecialchars($row["user_full_name"] ?? '') ?></td>
                <td><?= htmlspecialchars($row["municipality_name"] ?? '') ?></td>
                <td><?= htmlspecialchars($row["filled_date"] ?? '') ?></td>
                <td>
                    <form action="export_excel.php" method="get" style="margin:0;">
                        <input type="hidden" name="filled_id" value="<?= (int)($row["filled_data_id"] ?? 0) ?>">
                        <button type="submit" class="btn">Выгрузить в Excel</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php if (empty($filledRowsForJs)): ?>
            <tr><td colspan="5">Нет заполненных таблиц</td></tr>
        <?php endif; ?>
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
    <?php if (!empty($templatesList)): ?>
        <div class="template-select-wrapper" style="margin-bottom:15px;">
            <label for="templates-select">Загрузить сохранённый шаблон:</label>
            <select id="templates-select"
                    onchange="if(this.value){location.href='admin_view.php?template_id='+this.value;}">
                <option value="">-- выберите шаблон --</option>
                <?php foreach ($templatesList as $tpl): ?>
                    <?php
                    $id   = (int)($tpl['template_id'] ?? $tpl['id'] ?? 0);
                    $name = htmlspecialchars($tpl['template_name'] ?? $tpl['name'] ?? ('ID '.$id), ENT_QUOTES, 'UTF-8');
                    $isActive = !empty($tpl['is_active']);
                    $selected = (!empty($_GET['template_id']) && (int)$_GET['template_id'] === $id) ? 'selected' : '';
                    ?>
                    <option value="<?= $id ?>" <?= $selected ?>>
                        ID <?= $id ?> — <?= $name ?><?= $isActive ? ' (активный)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>
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
    <!-- Блок диаграммы -->
    <hr style="margin:30px 0;">

    <h2>Аналитика (диаграмма)</h2>

    <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:center; margin-bottom:15px;">
        <label>
            МО:
            <select id="moSelect" style="min-width:260px;">
                <option value="">-- выберите МО --</option>
            </select>
        </label>

        <label>
            Заполненная таблица:
            <select id="filledSelect" style="min-width:360px;" disabled>
                <option value="">-- сначала выберите МО --</option>
            </select>
        </label>

        <label>
            Показатель:
            <select id="indicatorSelect" style="min-width:360px;" disabled>
                <option value="">-- сначала выберите таблицу --</option>
            </select>
        </label>
    </div>

    <div style="background:#111; padding:15px; border-radius:10px;">
        <canvas id="filledChart" height="110"></canvas>
    </div>

    <!-- JS для диаграммы  -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const moSelect = document.getElementById("moSelect");
        const filledSelect = document.getElementById("filledSelect");
        const indicatorSelect = document.getElementById("indicatorSelect");
        const canvas = document.getElementById("filledChart");

        const municipalities = Array.isArray(window.municipalitiesList) ? window.municipalitiesList : [];
        const filledRows = Array.isArray(window.filledRowsForJs) ? window.filledRowsForJs : [];

        const EXCLUDE_KEYS = new Set(["Показатели", "Единица измерения", "Комментарий"]);

        function safeJsonParse(maybeJson) {
            if (maybeJson == null) return null;
            if (typeof maybeJson === "object") return maybeJson; // уже объект
            if (typeof maybeJson !== "string") return null;
            try { return JSON.parse(maybeJson); } catch { return null; }
        }

        function normalizeNumber(v) {
            if (v == null) return null;
            const s = String(v).trim().replace(",", ".");
            if (s === "") return null;
            const n = Number(s);
            return Number.isFinite(n) ? n : null;
        }

        // сортировка колонок типа: 2022, 2026_базовый, 2026_консервативный...
        function sortColumnKeys(keys) {
            return keys.sort((a, b) => {
                const pa = String(a).split("_");
                const pb = String(b).split("_");
                const ya = parseInt(pa[0], 10);
                const yb = parseInt(pb[0], 10);

                if (!Number.isNaN(ya) && !Number.isNaN(yb) && ya !== yb) return ya - yb;

                // если год одинаковый или нет года — сортируем по строке
                return String(a).localeCompare(String(b), "ru");
            });
        }

        function getColor(i, total) {
            const hue = Math.round((360 * i) / Math.max(1, total));
            return `hsl(${hue}, 70%, 55%)`;
        }

        let chart = new Chart(canvas, {
            type: "line",
            data: {
                labels: ["2022","2023","2024","2025"],
                datasets: [{
                    label: "Дефолтные данные",
                    data: [10, 20, 15, 25],
                    borderWidth: 2,
                    tension: 0.25
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: true }
                }
            }
        });

        function setDisabled(select, disabled, placeholder) {
            select.disabled = disabled;
            if (placeholder != null) {
                select.innerHTML = `<option value="">${placeholder}</option>`;
            }
        }

        municipalities.forEach(mo => {
            const opt = document.createElement("option");
            opt.value = mo.municipality_id;
            opt.textContent = mo.municipality_name;
            moSelect.appendChild(opt);
        });

        moSelect.addEventListener("change", () => {
            const moId = moSelect.value;

            setDisabled(filledSelect, true, "-- сначала выберите МО --");
            setDisabled(indicatorSelect, true, "-- сначала выберите таблицу --");

            if (!moId) {
                chart.data.labels = ["2022","2023","2024","2025"];
                chart.data.datasets = [{
                    label: "Дефолтные данные",
                    data: [10, 20, 15, 25],
                    borderWidth: 2,
                    tension: 0.25
                }];
                chart.update();
                return;
            }

            const list = filledRows.filter(r => String(r.municipality_id) === String(moId));
            filledSelect.innerHTML = `<option value="">-- выберите заполненную таблицу --</option>`;
            list.forEach(r => {
                const opt = document.createElement("option");
                opt.value = r.filled_data_id;
                opt.textContent = `#${r.filled_data_id} — ${r.template_name} — ${r.filled_date}`;
                filledSelect.appendChild(opt);
            });

            filledSelect.disabled = false;
        });

        function renderAllIndicators(filledDataObj) {
            // находим строки-«показатели»
            const rowsEntries = Object.entries(filledDataObj || {});
            const indicatorRows = rowsEntries
                .map(([rowIndex, rowObj]) => ({ rowIndex, rowObj }))
                .filter(x => x.rowObj && typeof x.rowObj === "object" && String(x.rowObj["Показатели"] || "").trim() !== "");

            if (!indicatorRows.length) {
                chart.data.labels = ["нет данных"];
                chart.data.datasets = [{ label: "Нет показателей", data: [0] }];
                chart.update();
                return;
            }

            // берём колонки (оси X) из первой строки показателя
            const first = indicatorRows[0].rowObj;
            let keys = Object.keys(first).filter(k => !EXCLUDE_KEYS.has(k));
            keys = sortColumnKeys(keys);

            const datasets = indicatorRows.map((item, idx) => {
                const label = String(item.rowObj["Показатели"] || `Строка ${item.rowIndex}`);
                const data = keys.map(k => normalizeNumber(item.rowObj[k]));
                const color = getColor(idx, indicatorRows.length);

                return {
                    label,
                    data,
                    borderColor: color,
                    backgroundColor: color,
                    borderWidth: 2,
                    tension: 0.25
                };
            });

            chart.data.labels = keys;
            chart.data.datasets = datasets;
            chart.update();
        }

        function renderOneIndicator(filledDataObj, rowIndex) {
            const rowObj = (filledDataObj || {})[rowIndex];
            if (!rowObj) return;

            let keys = Object.keys(rowObj).filter(k => !EXCLUDE_KEYS.has(k));
            keys = sortColumnKeys(keys);

            const label = String(rowObj["Показатели"] || "Показатель");
            const data = keys.map(k => normalizeNumber(rowObj[k]));

            chart.data.labels = keys;
            chart.data.datasets = [{
                label,
                data,
                borderWidth: 2,
                tension: 0.25
            }];
            chart.update();
        }
        filledSelect.addEventListener("change", () => {
            const filledId = filledSelect.value;

            setDisabled(indicatorSelect, true, "-- сначала выберите таблицу --");
            if (!filledId) return;

            const row = filledRows.find(r => String(r.filled_data_id) === String(filledId));
            if (!row) return;

            const filledDataObj = safeJsonParse(row.filled_data);
            if (!filledDataObj) {
                alert("Не удалось прочитать filled_data (JSON). Проверь данные в БД.");
                return;
            }

            // Заполняем показатели
            const indicatorRows = Object.entries(filledDataObj || {})
                .map(([rowIndex, rowObj]) => ({ rowIndex, rowObj }))
                .filter(x => x.rowObj && typeof x.rowObj === "object" && String(x.rowObj["Показатели"] || "").trim() !== "");

            indicatorSelect.innerHTML = `<option value="">-- показать все показатели (разными цветами) --</option>`;
            indicatorRows.forEach(item => {
                const opt = document.createElement("option");
                opt.value = item.rowIndex;
                opt.textContent = String(item.rowObj["Показатели"]);
                indicatorSelect.appendChild(opt);
            });

            indicatorSelect.disabled = false;

            // Сразу показываем все
            renderAllIndicators(filledDataObj);

            indicatorSelect.onchange = () => {
                const idx = indicatorSelect.value;
                if (!idx) {
                    renderAllIndicators(filledDataObj);
                } else {
                    renderOneIndicator(filledDataObj, idx);
                }
            };
        });
    });
    </script>
</body>
</html>