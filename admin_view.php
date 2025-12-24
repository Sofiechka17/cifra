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

/**
 * Репозиторий чтения данных для админ-страницы (SQL запросы).
 */
final class AdminViewRepository
{
    /** @var resource|\PgSql\Connection */
    private $conn;

    /**
     * Создаёт репозиторий.
     *
     * @param resource|\PgSql\Connection $conn Соединение PostgreSQL.
     */
    public function __construct($conn)
    {
        $this->conn = $conn;
    }

    /**
     * Возвращает все заполненные таблицы (для списка и аналитики).
     *
     * @return array<int, array<string, mixed>> Rows.
     */
    public function getFilledTables(): array
    {
        $sql = "
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
        ";

        $result = pg_query($this->conn, $sql);
        if (!$result) {
            return [];
        }

        $rows = [];
        while ($r = pg_fetch_assoc($result)) {
            $rows[] = $r;
        }
        return $rows;
    }

    /**
     * Возвращает все заявки обратной связи.
     *
     * @return resource|false PostgreSQL result resource or false.
     */
    public function getFeedbackResult()
    {
        $sql = "
            SELECT
                fr.feedback_id,
                fr.full_name_feedback,
                fr.phone_feedback,
                fr.problem_description_feedback
            FROM cit_schema.feedback_requests fr
            ORDER BY fr.feedback_id DESC
        ";

        return pg_query($this->conn, $sql);
    }

    /**
     * Возвращает список шаблонов для выпадающего списка.
     *
     * @return array<int, array<string, mixed>> Templates list.
     */
    public function getTemplatesList(): array
    {
        $sql = "
            SELECT template_id, template_name, is_active
            FROM cit_schema.table_templates
            ORDER BY template_id DESC
        ";

        $res = pg_query($this->conn, $sql);
        if (!$res) {
            return [];
        }

        $list = [];
        while ($row = pg_fetch_assoc($res)) {
            $list[] = $row;
        }
        return $list;
    }

    /**
     * Возвращает список муниципальных образований (МО).
     *
     * @return array<int, array<string, mixed>> Municipalities list.
     */
    public function getMunicipalitiesList(): array
    {
        $sql = "SELECT municipality_id, municipality_name FROM cit_schema.municipalities ORDER BY municipality_name";
        $res = pg_query($this->conn, $sql);
        if (!$res) {
            return [];
        }

        $list = [];
        while ($mo = pg_fetch_assoc($res)) {
            $list[] = $mo;
        }
        return $list;
    }
}

/**
 * Сборка данных (view-model) для admin_view.php.
 */
final class AdminViewPage
{
    /** @var TemplateService */
    private TemplateService $templateService;

    /** @var AdminViewRepository */
    private AdminViewRepository $repo;

    /**
     * Создаёт страницу.
     *
     * @param TemplateService $templateService Сервис шаблонов.
     * @param AdminViewRepository $repo Репозиторий данных.
     */
    public function __construct(TemplateService $templateService, AdminViewRepository $repo)
    {
        $this->templateService = $templateService;
        $this->repo = $repo;
    }

    /**
     * Загружает шаблон по template_id из GET .
     *
     * @param array<string, mixed> $query GET-параметры.
     * @return array<string, mixed>|null DTO для JS или null.
     */
    public function loadTemplateForJs(array $query): ?array
    {
        if (empty($query['template_id'])) {
            return null;
        }

        $tplId = (int)$query['template_id'];
        if ($tplId <= 0) {
            return null;
        }

        try {
            $templateObj = $this->templateService->getTemplateById($tplId);
            if (!$templateObj) {
                return null;
            }

            return [
                'headers' => $templateObj->getHeaders(),
                'structure' => $templateObj->getStructure(),
                'template_name' => $templateObj->getName(),
                'template_id' => $templateObj->getId(),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Собирает все данные, необходимые для рендера страницы.
     *
     * @param array<string, mixed> $query GET-параметры.
     * @return array<string, mixed> ViewModel.
     */
    public function buildViewModel(array $query): array
    {
        $filledRows = $this->repo->getFilledTables();
        $feedbackResult = $this->repo->getFeedbackResult();
        $templatesList = $this->repo->getTemplatesList();
        $municipalitiesList = $this->repo->getMunicipalitiesList();
        $loadedTemplateArray = $this->loadTemplateForJs($query);

        return [
            'filledRowsForJs' => $filledRows,
            'feedbackResult' => $feedbackResult,
            'templatesList' => $templatesList,
            'municipalitiesList' => $municipalitiesList,
            'loadedTemplateArray' => $loadedTemplateArray,
        ];
    }
}

$service = new TemplateService($conn);
$repo = new AdminViewRepository($conn);
$page = new AdminViewPage($service, $repo);

$vm = $page->buildViewModel($_GET);

$filledRowsForJs = $vm['filledRowsForJs'];
$feedbackResult = $vm['feedbackResult'];
$templatesList = $vm['templatesList'];
$municipalitiesList = $vm['municipalitiesList'];
$loadedTemplateArray = $vm['loadedTemplateArray'];
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

    <?php if ($feedbackResult): ?>
        <?php while ($fb = pg_fetch_assoc($feedbackResult)): ?>
            <tr>
                <td><?= htmlspecialchars((string)$fb["feedback_id"]) ?></td>
                <td><?= htmlspecialchars((string)$fb["full_name_feedback"]) ?></td>
                <td><?= htmlspecialchars((string)$fb["phone_feedback"]) ?></td>
                <td><?= nl2br(htmlspecialchars((string)$fb["problem_description_feedback"])) ?></td>
            </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="4">Нет данных обратной связи</td></tr>
    <?php endif; ?>
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
    Чтобы объединить ячейки: зажмите <b>Shift</b> и кликните сначала по первой ячейке диапазона, потом по последней.
    Все ячейки внутри прямоугольника выделятся — после этого нажмите кнопку «Объединить выбранные ячейки».<br>
    Чтобы разъединить: таким же образом выделите диапазон и нажмите «Разъединить ячейки».<br>
    Кнопки «Удалить выбранные строки» и «Удалить выбранные столбцы» удаляют строки/столбцы, попадающие в выделенный диапазон.
</p>

<?php if (!empty($templatesList)): ?>
    <div class="template-select-wrapper" style="margin-bottom:15px;">
        <label for="templates-select">Загрузить сохранённый шаблон:</label>
        <select id="templates-select"
                onchange="if(this.value){location.href='admin_view.php?template_id='+this.value;}">
            <option value="">-- выберите шаблон --</option>
            <?php foreach ($templatesList as $tpl): ?>
                <?php
                $id = (int)($tpl['template_id'] ?? $tpl['id'] ?? 0);
                $name = htmlspecialchars($tpl['template_name'] ?? $tpl['name'] ?? ('ID ' . $id), ENT_QUOTES, 'UTF-8');
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
            <input type="checkbox" id="make-active-checkbox"> Сделать этот шаблон активным
        </label>
    </div>

    <div class="rows-cols-wrapper">
        <label>
            Количество строк: <input type="number" id="rows-count" min="1" max="200" value="5">
        </label>
        <label>
            Количество столбцов: <input type="number" id="cols-count" min="1" max="20" value="7">
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
        <table id="constructor-table"><!-- JS строит таблицу --></table>
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