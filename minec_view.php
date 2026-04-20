<?php
/**
 * Страница пользователя минэк
 * Отображает:
 * - список заполненных пользователями таблицами с возможностью выгрузки в Excel
 * - аналитика по заполненным таблицам (Chart.js)
 */

require_once __DIR__ . '/bootstrap.php';

(new SessionGuard())->requireMinec();

$repo = new AdminViewRepository($conn);
$filledRowsForJs = $repo->getFilledTables();
$municipalitiesList = $repo->getMunicipalitiesList();

$loadedTemplateArray = null;
if (!empty($_GET['template_id'])) {
    $tplId = (int)$_GET['template_id'];
    if ($tplId > 0) {
        $service = new TemplateService($conn);
        $tpl = $service->getTemplateById($tplId);
        if ($tpl->getId() !== 0) {
            $loadedTemplateArray = [
                'headers'       => $tpl->getHeaders(),
                'structure'     => $tpl->getStructure(),
                'template_name' => $tpl->getName(),
                'template_id'   => $tpl->getId(),
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Минэк - отчеты и диаграммы</title>
    <link rel="stylesheet" href="styles.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Передаём в JS загруженный из БД шаблон (если есть) -->
    <script>
        window.initialTemplate = <?= $loadedTemplateArray ? json_encode($loadedTemplateArray, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) : 'null'; ?>;
        window.municipalitiesList = <?= json_encode($municipalitiesList, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        window.filledRowsForJs = <?= json_encode($filledRowsForJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        window.csrfToken = <?= json_encode(Csrf::token()) ?>;
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

<script>
/**
 * Класс аналитики (управляет комбобоксами и графиком Chart.js).
 */
class FilledAnalytics {
    /**
     * @param {HTMLSelectElement} moSelect
     * @param {HTMLSelectElement} filledSelect
     * @param {HTMLSelectElement} indicatorSelect
     * @param {HTMLCanvasElement} canvas
     * @param {Array<Object>} municipalities
     * @param {Array<Object>} filledRows
     */
    constructor(moSelect, filledSelect, indicatorSelect, canvas, municipalities, filledRows) {
        this.moSelect = moSelect;
        this.filledSelect = filledSelect;
        this.indicatorSelect = indicatorSelect;
        this.canvas = canvas;
        this.municipalities = Array.isArray(municipalities) ? municipalities : [];
        this.filledRows = Array.isArray(filledRows) ? filledRows : [];

        this.EXCLUDE_KEYS = new Set(["Показатели", "Единица измерения", "Комментарий"]);
        this.chart = null;
    }

    /** Инициализирует UI */
    init() {
        if (!this.moSelect || !this.filledSelect || !this.indicatorSelect || !this.canvas) return;

        this.initChart();
        this.fillMunicipalities();
        this.bindEvents();
    }

    /** Создаёт дефолтный график. */
    initChart() {
        this.chart = new Chart(this.canvas, {
            type: "line",
            data: {
                labels: ["2022", "2023", "2024", "2025"],
                datasets: [{ label: "Дефолтные данные", data: [10, 20, 15, 25], borderWidth: 2, tension: 0.25 }]
            },
            options: { responsive: true, plugins: { legend: { display: true } } }
        });
    }

    /** Заполняет список МО. */
    fillMunicipalities() {
        this.municipalities.forEach(mo => {
            const opt = document.createElement("option");
            opt.value = mo.municipality_id;
            opt.textContent = mo.municipality_name;
            this.moSelect.appendChild(opt);
        });
    }

    /** Подписывает обработчики событий комбобоксов. */
    bindEvents() {
        this.moSelect.addEventListener("change", () => this.onMunicipalityChange());
        this.filledSelect.addEventListener("change", () => this.onFilledChange());
    }

    /**
     * Безопасный парсинг JSON/объекта.
     * @param {any} maybeJson
     * @returns {Object|null}
     */
    safeJsonParse(maybeJson) {
        if (maybeJson == null) return null;
        if (typeof maybeJson === "object") return maybeJson;
        if (typeof maybeJson !== "string") return null;
        try { return JSON.parse(maybeJson); } catch { return null; }
    }

    /**
     * Приводит значение к числу.
     * @param {any} v
     * @returns {number|null}
     */
    normalizeNumber(v) {
        if (v == null) return null;
        const s = String(v).trim().replace(",", ".");
        if (s === "") return null;
        const n = Number(s);
        return Number.isFinite(n) ? n : null;
    }

    /**
     * Сортирует колонки вида: 2022, 2026_базовый, 2026_консервативный...
     * @param {string[]} keys
     * @returns {string[]}
     */
    sortColumnKeys(keys) {
        return keys.sort((a, b) => {
            const pa = String(a).split("_");
            const pb = String(b).split("_");
            const ya = parseInt(pa[0], 10);
            const yb = parseInt(pb[0], 10);
            if (!Number.isNaN(ya) && !Number.isNaN(yb) && ya !== yb) return ya - yb;
            return String(a).localeCompare(String(b), "ru");
        });
    }

    /**
     * Цвет линии набора данных.
     * @param {number} i
     * @param {number} total
     * @returns {string}
     */
    getColor(i, total) {
        const hue = Math.round((360 * i) / Math.max(1, total));
        return `hsl(${hue}, 70%, 55%)`;
    }

    /**
     * Устанавливает disabled и плейсхолдер для select.
     * @param {HTMLSelectElement} select
     * @param {boolean} disabled
     * @param {string|null} placeholder
     */
    setDisabled(select, disabled, placeholder) {
        select.disabled = disabled;
        if (placeholder != null) {
            select.innerHTML = `<option value="">${placeholder}</option>`;
        }
    }

    /** Обработчик смены МО. */
    onMunicipalityChange() {
        const moId = this.moSelect.value;

        this.setDisabled(this.filledSelect, true, "-- сначала выберите МО --");
        this.setDisabled(this.indicatorSelect, true, "-- сначала выберите таблицу --");

        if (!moId) {
            this.renderDefault();
            return;
        }

        const list = this.filledRows.filter(r => String(r.municipality_id) === String(moId));
        this.filledSelect.innerHTML = `<option value="">-- выберите заполненную таблицу --</option>`;

        list.forEach(r => {
            const opt = document.createElement("option");
            opt.value = r.filled_data_id;
            opt.textContent = `#${r.filled_data_id} — ${r.template_name} — ${r.filled_date}`;
            this.filledSelect.appendChild(opt);
        });

        this.filledSelect.disabled = false;
    }

    /** Рендер дефолтных данных. */
    renderDefault() {
        this.chart.data.labels = ["2022", "2023", "2024", "2025"];
        this.chart.data.datasets = [{ label: "Дефолтные данные", data: [10, 20, 15, 25], borderWidth: 2, tension: 0.25 }];
        this.chart.update();
    }

    /**
     * Рисует все показатели (каждый как отдельная линия).
     * @param {Object} filledDataObj
     */
    renderAllIndicators(filledDataObj) {
        const rowsEntries = Object.entries(filledDataObj || {});
        const indicatorRows = rowsEntries
            .map(([rowIndex, rowObj]) => ({ rowIndex, rowObj }))
            .filter(x => x.rowObj && typeof x.rowObj === "object" && String(x.rowObj["Показатели"] || "").trim() !== "");

        if (!indicatorRows.length) {
            this.chart.data.labels = ["нет данных"];
            this.chart.data.datasets = [{ label: "Нет показателей", data: [0] }];
            this.chart.update();
            return;
        }

        const first = indicatorRows[0].rowObj;
        let keys = Object.keys(first).filter(k => !this.EXCLUDE_KEYS.has(k));
        keys = this.sortColumnKeys(keys);

        const datasets = indicatorRows.map((item, idx) => {
            const label = String(item.rowObj["Показатели"] || `Строка ${item.rowIndex}`);
            const data = keys.map(k => this.normalizeNumber(item.rowObj[k]));
            const color = this.getColor(idx, indicatorRows.length);
            return { label, data, borderColor: color, backgroundColor: color, borderWidth: 2, tension: 0.25 };
        });

        this.chart.data.labels = keys;
        this.chart.data.datasets = datasets;
        this.chart.update();
    }

    /**
     * Рисует один выбранный показатель.
     * @param {Object} filledDataObj
     * @param {string} rowIndex
     */
    renderOneIndicator(filledDataObj, rowIndex) {
        const rowObj = (filledDataObj || {})[rowIndex];
        if (!rowObj) return;

        let keys = Object.keys(rowObj).filter(k => !this.EXCLUDE_KEYS.has(k));
        keys = this.sortColumnKeys(keys);

        const label = String(rowObj["Показатели"] || "Показатель");
        const data = keys.map(k => this.normalizeNumber(rowObj[k]));

        this.chart.data.labels = keys;
        this.chart.data.datasets = [{ label, data, borderWidth: 2, tension: 0.25 }];
        this.chart.update();
    }

    /** Обработчик смены заполненной таблицы. */
    onFilledChange() {
        const filledId = this.filledSelect.value;
        this.setDisabled(this.indicatorSelect, true, "-- сначала выберите таблицу --");
        if (!filledId) return;

        const row = this.filledRows.find(r => String(r.filled_data_id) === String(filledId));
        if (!row) return;

        const filledDataObj = this.safeJsonParse(row.filled_data);
        if (!filledDataObj) {
            alert("Не удалось прочитать filled_data (JSON). Проверь данные в БД.");
            return;
        }

        const indicatorRows = Object.entries(filledDataObj || {})
            .map(([rowIndex, rowObj]) => ({ rowIndex, rowObj }))
            .filter(x => x.rowObj && typeof x.rowObj === "object" && String(x.rowObj["Показатели"] || "").trim() !== "");

        this.indicatorSelect.innerHTML = `<option value="">-- показать все показатели (разными цветами) --</option>`;
        indicatorRows.forEach(item => {
            const opt = document.createElement("option");
            opt.value = item.rowIndex;
            opt.textContent = String(item.rowObj["Показатели"]);
            this.indicatorSelect.appendChild(opt);
        });

        this.indicatorSelect.disabled = false;
        this.renderAllIndicators(filledDataObj);

        this.indicatorSelect.onchange = () => {
            const idx = this.indicatorSelect.value;
            if (!idx) this.renderAllIndicators(filledDataObj);
            else this.renderOneIndicator(filledDataObj, idx);
        };
    }
}

document.addEventListener("DOMContentLoaded", () => {
    const analytics = new FilledAnalytics(
        document.getElementById("moSelect"),
        document.getElementById("filledSelect"),
        document.getElementById("indicatorSelect"),
        document.getElementById("filledChart"),
        window.municipalitiesList,
        window.filledRowsForJs
    );
    analytics.init();
});
</script>

</body>
</html>